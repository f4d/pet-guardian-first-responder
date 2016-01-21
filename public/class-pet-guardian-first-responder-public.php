<?php
class Pet {
	public $petOwnerId, $petfile;
	public function __construct( $petfile, $petOwnerId ) {
		$this->petOwnerId = $petOwnerId;
		$this->petfile = $petfile;
		$this->guardians = array();
	}
	public function setGuardian($guardianNum,$data) {
		$this->guardians[$guardianNum] = new Guardian($data);
	}
	public function findPetfileUrl() {
		$petfileArr = array('1'=>'6','2'=>'57','3'=>'58','4'=>'59','5'=>'60');
		//use $this->petfile & $this->petOwnerId
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
class PhoneNumber {
	const FORM_ID = '68';
	const PHONE_FIELD = '4';
	const HEALTH_FIELD = '2';
	const USER_ID_FIELD = '3';

	public $number;
	public $health; 
	public $userId;
	public function __construct($number,$health="unknown",$userId=0) {
		$this->number = $number;
		$this->health = $health;
		$this->userId = $userId;
	}
	public static function validNumber($number) {
		$test = (string) preg_replace("/[^0-9]/", "", $number);
		if ( $test == '5555555555' || $test == '0000000000' || strlen($test) != 10 ) {
			return false;
		}
		return true;
	}
	public static function scrubPhone($number) {
		//strip all non-numeric characters out, and prepend a +1
		$number = preg_replace("/[^0-9]/", "", $number);
		return '+1'.$number;
	}
	public static function lookup($number, $userId = 0) {
		$records = PhoneNumber::gfFindNumber($number);
		if (count($records)>0) {
			$entry = array_pop($records);
			$pn = new PhoneNumber($number,$entry[PhoneNumber::HEALTH_FIELD]);
		} else {
			$pn = new PhoneNumber($number,'unknown',$userId);
			if (!PhoneNumber::validNumber($number)) {
				$pn->health = "bad";
			}
			$pn->save(); 
		}
		return $pn;	
	}
	public function save() {
		$entry = array();
		$entry['form_id'] = PhoneNumber::FORM_ID;
		$entry[PhoneNumber::PHONE_FIELD] = $this->number;
		$entry[PhoneNumber::HEALTH_FIELD] = $this->health;
		$entry[PhoneNumber::USER_ID_FIELD] = $this->userId;
		GFAPI::add_entry( $entry );
	}
	public function update() {
		if ($this->userId == 0) {
			//update all of the numbers
			$entries = PhoneNumber::gfFindNumber($this->number);
			foreach ($entries as $e) {
				GFAPI::update_entry( $e );
			}
		} 
	}
	public static function gfFind($number,$userId=0) {
		$search_criteria = array();
		$search_criteria['field_filters'][] = array( 'key' => PhoneNumber::PHONE_FIELD, 'value' => $number );
		if($userId != 0) {
			$search_criteria['field_filters'][] = array( 'key' => PhoneNumber::USER_ID_FIELD, 'value' => $userId );
		}
		$entries = GFAPI::get_entries( PhoneNumber::FORM_ID, $search_criteria );
		return $entries;
	}
	public static function gfFindNumber($number) {
		$search_criteria = array();
		$search_criteria['field_filters'][] = array( 'key' => PhoneNumber::PHONE_FIELD, 'value' => $number );
		$entries = GFAPI::get_entries( PhoneNumber::FORM_ID, $search_criteria );
		return $entries;
	}
}
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
	public function testPhoneNumber() {
		$a = PhoneNumber::gfFindNumber('7736411561');
		$b = PhoneNumber::gfFind('7736092730','1');
		$c = PhoneNumber::gfFind('7736411561','1');

		print_r(count($a));
		echo "<br>";
		print_r(count($b));
		echo "<br>";
		print_r(count($c));		
		echo "<br>";

		$pn = PhoneNumber::lookup('7736092730','1');
		print_r($pn);
		//
		$pn = PhoneNumber::lookup('0000000000','1');
		print_r($pn);
		print_r(PhoneNumber::gfFindNumber('0000000000'));
		//
		$pn = PhoneNumber::lookup('1234567890','1');
		print_r($pn);
		print_r(PhoneNumber::gfFindNumber('1234567890'));

	}
	public function filterConfirmation($confirmation,$form,$entry) {
		$confirmation = $entry['14'];
		return $confirmation;
	}
	public function filterGform($form) {
		$pet_owner_id = $_POST['input_11'];
		$user = $this->findUser($pet_owner_id);
		//if user not valid
		if($user===false) {
			$this->createConfirmation('false',"<p><b>Error: Invalid user ID provided, messages not sent!</b></p>");
			return 0;
		} else {
			$data = get_metadata('user', $user->ID);
			$primary = $data['mobile_phone'][0];
			$pets = array();
			$numPets = $this->numOfPets($data);
			for($i=1;$i<($numPets+1);$i++) {
				$pets[$i] = $this->getPet($pet_owner_id,$i,$data);
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
		  PhoneNumber::scrubPhone($to), // Text any number
		  $str
		);
		//print_r($message);
		$sid = $message->sid;		
	}

	public function findUser($pet_owner_id) {
		$user = false;
		$query = new WP_User_Query( array( 'meta_key' => 'pet_owner_id', 'meta_value' => $pet_owner_id ) );
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
		$okay = 'true';
		$primary = $this->alertPrimary($str,$primary);
		if($primary == 0) {
			$msg = "Warning: We were unable to send a message to the primary pet owner. ";
		} else {
			$msg = "Message sent to the primary pet owner. ";
		}
		$alerted = $this->alertGuardians($str,$pets);
		if($alerted->sent > 0 ) {
			$msg .= "You successfully sent ".$alerted->sent." messages to Pet Guardians. ";
		}
		if($alerted->failed > 0) {
			$msg .= "Warning: We were unable to send ".$alerted->failed." messages to Pet Guardians. ";
		}
		if ($primary == 0 && $alerted->sent == 0) {$okay = 'false';}
		$this->createConfirmation($okay,$msg);
	}

	public function getPet($petOwnerId,$petNum,$data) {
		$pet = new Pet($petNum,$petOwnerId);
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
		  echo 'Caught exception: ',  $e->getMessage(), "\n";
			return 0;
		}
		return 1;
	}
	public function alertGuardians($str,$pets,$userId) {
		$alerts = new StdClass;
		$alerts->sent = 0;
		$alerts->total = 0;
		foreach($pets as $p) {
			foreach($p->guardians as $g) {
				//check the number, if it's new, save to the db
				$phoneNumber = PhoneNumber::lookup($g->mobile_phone,$userId);
				if($g->response==='1' && $phoneNumber->health != "bad") {
					$alerts->sent++;
					$alerts->total++;
					try {
					  $this->twilioMessage($str,$phoneNumber->number);
					} catch (Exception $e) {
						$alerts->sent--;
						echo 'Caught exception: ',  $e->getMessage(), "\n";
						mail ( 'cyborgk@gmail.com' , 'Bad Number: Pet Guardian' , $e->getMessage() );
					}
				}
			}
		}
		$alerts->failed = $alerts->total - $alerts->sent;
		return $alerts;
	}

	private function numOfPets($data) {
		return $data['how_many_pets_owned'][0];
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
