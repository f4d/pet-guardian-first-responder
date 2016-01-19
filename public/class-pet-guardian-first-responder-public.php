<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       http://example.com
 * @since      1.0.0
 *
 * @package    Pet_Guardian_First_Responder
 * @subpackage Pet_Guardian_First_Responder/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Pet_Guardian_First_Responder
 * @subpackage Pet_Guardian_First_Responder/public
 * @author     Your Name <email@example.com>
 */
class Pet_Guardian_First_Responder_Public {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of the plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version = $version;
	}
	public function filterConfirmation($confirmation,$form,$entry) {
		return $confirmation .' '. $entry['14'];
	}
	public function filterGform($form) {
		$user = $this->findUser();
		//if user not valid
		if($user===false) {
			$this->createConfirmation('false',"<p>Invalid user ID provided. This angers me!</p>");
			return 0;
		} else {
			$data = get_metadata(user, $user->ID);
			$primary = $data['mobile_phone'][0];
			$pets = array();
			$numPets = $this->numOfPets($data);
			for($i=1;$i<($numPets+1);$i++) {
				$pets[$i] = $this->getPet($user->ID,$i,$data);
			}
			$str = $this->createMessage();
			$this->sendAlerts($str,$primary,$pets);
		}
		
	}
	public function createMessage() {
		$name = $_POST['input_6_2'].' '.$_POST['input_6_3'].' '.$_POST['input_6_6'];
		$msg = $_POST['input_10'].'. '.$_POST['input_8'];
		$str = "Pet Guardian Alert! Message from First Responder $name, Phone:$msg";
		return $str;
	}
	public function twilioMessage($str,$to) {
		$account_sid = "ACb7c5f3d51adb05223c640ffaff969b46"; // Your Twilio account sid
		$auth_token = "d54280461d5603d9cc2217ca2b79ab62"; // Your Twilio auth token
		$client = new Services_Twilio($account_sid, $auth_token);
		$message = $client->account->messages->sendMessage(
		  '+13134448630', // From a Twilio number in your account
		  $this->scrubPhone($to), // Text any number
		  $str
		);
		$sid = $message->sid;		
	}

	public function findUser() {
		$query = $this->getUserByMetaId($_POST['entry_11']);
		$user = false;
		if (count($query->results) == 1) {
			$user = $query->results[0];
		}
		return $user;
	}
	public function createConfirmation($successful,$message) {
		$_POST['input_13'] = $successful;
		$_POST['input_14'] = $message;
	}
	public function sendAlerts($str,$primary,$pets) {
		$msg = '';
		$okay = 'true';
		$primary = $this->alertPrimary($str,$primary);
		if($primary == 0) {
			$msg .= "Warning: We were unable to send a message to the primary pet owner. ";
		}
		$alerted = $this->alertGuardians($str,$pets);
		if($alerted->sent > 0 ) {
			$msg .= "You successfully sent ".$alerted->sent." messages to Pet Guardians. ";
		}
		if($alerted->failed > 0) {
			$msg = "Warning: We were unable to send ".$alerted->failed." messages to Pet Guardians. ";
		}
		if ($primary == 0 && $alerted->sent == 0) {$okay = 'false';}
		$this->createConfirmation($okay,$msg);
	}
	public function getUserByMetaId($ownerId) {
		$user = new WP_User_Query( array( 'meta_key' => 'pet_owner_id', 'meta_value' => $ownerId ) );
		return $user;
	}
	public function getPet($userId,$petNum,$data) {
		$pet = new Pet($petNum);
		for($i=1;$i<6;$i++) {
			$prefix = "p{$petNum}_guardian_{$i}_";
			$arr = array('prefix','first_name','last_name','email','mobile_phone','response');
			$hash = array();
			foreach($arr as $a) {
				$str = $prefix.$a;
				$hash[$a] = $data[$str][0];
			}

			$pet->setGuardian($i,$hash);
		}
		return $pet;
	}
	public function alertPrimary($str,$number) {
		try {
		    $this->twilioMessage($str,$number);
		} catch (Exception $e) {
		    //echo 'Caught exception: ',  $e->getMessage(), "\n";
			return 0;
		}
		return 1;
	}
	public function alertGuardians($str,$pets) {
		$alerts = new StdClass;
		$alerts->sent = 0;
		$alerts->total = 0;
		foreach($pets as $p) {
			foreach($p->guardians as $g) {
				if($g->response==='1' && $this->validNumber($g->mobile_phone)) {
					$alerts->sent++;
					$alerts->total++;
					try {
					    $this->twilioMessage($str,$g->mobile_phone);
					} catch (Exception $e) {
						$alerts->sent--;
						//echo 'Caught exception: ',  $e->getMessage(), "\n";
					}
				}
			}
		}
		$alerts->failed = $alerts->total - $alerts->sent;
		return $alerts;
	}
	public function validNumber($number) {
		$test = (string) preg_replace("/[^0-9]/", "", $number);
		if ( $test == '5555555555' || strlen($test) != 10 ) {
			return false;
		}
		return true;
	}
	public function scrubPhone($number) {
		//strip all non-numeric characters out, and prepend a +1
		$number = preg_replace("/[^0-9]/", "", $number);
		return '+1'.$number;
	}
	private function numOfPets($data) {
		return 5;
	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Plugin_Name_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Plugin_Name_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/pet-guardian-first-responder-public.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Plugin_Name_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Plugin_Name_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/pet-guardian-first-responder-public.js', array( 'jquery' ), $this->version, false );

	}

}
class Pet {
	public function __construct( $which ) {
		$this->which = $which;
		$this->guardians = array();
	}
	public function setGuardian($which,$data) {
		$this->guardians[$which] = new Guardian($data);
	}
}
class Guardian {
	public $prefix, $first_name, $last_name, $email, $mobile_phone, $response;
	public function __construct( $data ) {
		$this->prefix = $data['prefix'];
		$this->first_name = $data['first_name'];
		$this->last_name = $data['last_name'];
		$this->email = $data['email'];
		$this->mobile_phone = $data['mobile_phone'];
		$this->response = $data['response'];
	}
}
