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

	public function filterGform($entry) {
		$query = $this->getUserByMetaId($entry['11']);
		//$user = $query->results[0]->data->ID;
		$user = $query->results[0];
		$data = get_metadata(user, $user->ID);
		//$data = get_user_meta($user->ID);
		$pets = array();
		for($i=1;$i<6;$i++) {
			$pets[$i] = $this->getPet($user->ID,$i,$data);
		}
		$str = $this->createMessage($entry);
		//alert primary, then guardians
		$this->alertGuardians($str,$pets);
	}
	public function createMessage($entry) {
		$name = $entry['6.2'].' '.$entry['6.3'].' '.$entry['6.6'];
		$msg = $entry['10'].'. '.$entry['8'];
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

	public function getUserByMetaId($petId) {
		//$users = new WP_User_Query( array( 'meta_key' => 'pet_1_id', 'meta_value' => $petId ) );
		$user = new WP_User_Query( array( 'meta_key' => 'pet_owner_id', 'meta_value' => $petId ) );
		return $user;
	}
	public function getPet($userId,$petNum,$data) {
		$pet = new Pet($petNum);
		//print_r($data);
		echo "<br><br>";


		for($i=1;$i<6;$i++) {
			$prefix = "p{$petNum}_guardian_{$i}_";
			$arr = array('prefix','first_name','last_name','email','mobile_phone','response');
			$hash = array();
			foreach($arr as $a) {
				$str = $prefix.$a;
				//print_r($str).":";
				//print_r($data[$str][0]);
				//echo "<br>";
				$hash[$a] = $data[$str][0];
			}

			$pet->setGuardian($i,$hash);
		}
		return $pet;
	}
	public function alertGuardians($str,$pets) {
		echo "<br>";
		foreach($pets as $p) {
			foreach($p->guardians as $g) {
				//print_r($g);
				//echo "<br>";
				if($g->response==='1') {
					//echo $this->scrubPhone($g->mobile_phone);
					try {
					    $this->twilioMessage($str,$g->mobile_phone);
					} catch (Exception $e) {
					    //echo 'Caught exception: ',  $e->getMessage(), "\n";
					}
				}
			}
		}
	}
	public function scrubPhone($number) {
		//strip all non-numeric characters out, and prepend a 1
		$number = preg_replace("/[^0-9]/", "", $number);
		return '+1'.$number;
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
