<?php
class UserHelper {
	const PRIMARY_NUM_KEY = "user_mobile_phone";
	static public function guardianMobileKey($petNum,$guardianNum) {
		return "p{$petNum}_guardian_{$guardianNum}_mobile_phone";
	}
	static public function updateGuardianNumber($userId,$petNum,$guardianNum,$newNum) {
		$key = UserHelper::guardianMobileKey($petNum,$guardianNum);
		update_user_meta( $userId, $key, $newNum );
	}
	static public function updatePrimaryNumber($userId,$newNum) {
		$key = UserHelper::PRIMARY_NUM_KEY;
		update_user_meta( $userId, $key, $newNum );
	}
	static public function updateNumbers($userId,$meta,$oldNum,$newNum) {
		//check primary number
		if($meta[UserHelper::PRIMARY_NUM_KEY] == $oldNum) {
			UserHelper::updatePrimaryNumber($userId,$newNum);
		} 
		for($i=1;$i<($numPets+1);$i++) {
			for($j=1;$j<6;$j++) {
				$field = UserHelper::guardianMobileKey($i,$j);
				$currentNum = $meta[$field];
				//if the number matches, update it
				if($currentNum == $oldNum) {
					UserHelper::updateGuardianNumber($userId,$i,$j,$newNum);
				}
			}
		}		
	}
}
class TwilioHelper {
	const SUCCESS_FIELD = 'input_13';
	const MESSAGE_FIELD = 'input_14';
	static public function createConfirmation($successful,$message) {
		$_POST[SUCCESS_FIELD] = $successful;
		$_POST[MESSAGE_FIELD] = $message;
	}
	static public function 	createMessage($post,$pets) {
		$name = $post['input_6_2'].' '.$post['input_6_3'].' '.$post['input_6_6'];
		$phone .= $post['input_8'];
		$msg = ' View the petfile(s) at '.TwilioHelper::petfileUrls($pets);
		$msg .= $post['input_10'];
		$str = "Pet Guardian Alert! Message from First Responder $name, Phone: $phone. $msg";
		return $str;
	}
	static public function petfileUrls($pets) {
		$str = '';
		foreach($pets as $pet) {
			$str .= $pet->findPetfileUrl().' ';
		}
	}
	static public function sendMsg($str,$to) {
		$account_sid = "ACb7c5f3d51adb05223c640ffaff969b46"; // Your Twilio account sid
		$auth_token = "d54280461d5603d9cc2217ca2b79ab62"; // Your Twilio auth token
		$client = new Services_Twilio($account_sid, $auth_token);
		$message = $client->account->messages->create(array( 
			'To' => PhoneNumber::scrubPhone($to), 
			'From' => " +13134448630", 
			'Body' => $str, 
			'StatusCallback' => "http://www.millionpetchallenge.com/wp-json/petguardian/v1/twilio-response" 
		));

		$sid = $message->sid;
	}
}
class Pet {
	const PET_OWNER_FIELD = '204';
	const PF1_ID = '6';
	const PF2_ID = '57';
	const PF3_ID = '58';
	const PF4_ID = '59';
	const PF5_ID = '60';
	public $petOwnerId, $petfile;
	public function __construct( $petfile, $petOwnerId, $data ) {
		$this->petOwnerId = $petOwnerId;
		$this->petfile = $petfile;
		$this->guardians = array();
		$this->data = $data;
	}
	public function setGuardian($guardianNum,$data) {
		$this->guardians[$guardianNum] = new Guardian($data);
	}
	public function findPetfileUrl() {
		$petfileArr = array('1'=>Pet::PF1_ID,'2'=>Pet::PF2_ID,'3'=>Pet::PF3_ID,
			'4'=>Pet::PF4_ID,'5'=>Pet::PF5_ID);
		//use $this->petfile & $this->petOwnerId, lookup in petfile{n} gravityform
		$search_criteria = array();
		$search_criteria['field_filters'][] = array( 
			'key' => Pet::PET_OWNER_FIELD, 
			'value' => $this->petOwnerId );
		$entries = GFAPI::get_entries( $petfileArr[$this->petfile], $search_criteria );
		$last = array_shift($entries);
		return 'http://millionpetchallenge.com/guardian-access-petfile-1/?eid='.$last['id'];
	}
	static public function numOfPets($data) {
		return $data['how_many_pets_owned'][0];
	}
	static public function getPet($petOwnerId,$petNum,$data) {
		$pet = new Pet($petNum,$petOwnerId,$data);
		//set info for each of the pet guardians
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
		$this->number = PhoneNumber::cleanup($number);
		$this->health = $health;
		$this->userId = $userId;
	}
	public function setHealth($callStatus) {
		if ($callStatus=="sent" || $callStatus=="delivered") {
			$this->health = "good";
		} else if($callStatus=="failed" || $callStatus=="undelivered") {
			$this->health = "bad";
		}
	}
	public static function validNumber($number) {
		$test = (string) preg_replace("/[^0-9]/", "", $number);
		if ( $test == '5555555555' || $test == '0000000000' || strlen($test) != 10 ) {
			return false;
		}
		return true;
	}	
	public static function stripPrefix($number) {
		$arr = str_split($number);
		array_shift ( $arr );
		array_shift ( $arr );
		return implode($arr);
	}
	public static function cleanup($number) {
		//strip all non-numeric characters out
		return preg_replace("/[^0-9]/", "", $number);
	}
	public static function scrubPhone($number) {
		return '+1'.$number;
	}
	public static function lookup($number, $userId = 0) {
		$number = PhoneNumber::cleanup($number);		
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
			foreach ($entries as $entry) {
				$entry[PhoneNumber::PHONE_FIELD] = $this->number;
				$entry[PhoneNumber::HEALTH_FIELD] = $this->health;
				$entry[PhoneNumber::USER_ID_FIELD] = $this->userId;
				GFAPI::update_entry( $entry );
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
	public static function gfFindNumber($number,$last=false) {
		$search_criteria = array();
		$search_criteria['field_filters'][] = array( 'key' => PhoneNumber::PHONE_FIELD, 'value' => $number );
		$entries = GFAPI::get_entries( PhoneNumber::FORM_ID, $search_criteria );
		if($last) {return $entries[0];}
		else {return $entries;}
	}
	public static function updateNumberHealth($number,$callStatus) {
		if ($callStatus == 'sent' || $callStatus == 'failed' || $callStatus == 'undelivered' || $callStatus == 'delivered') {
			$p = PhoneNumber::gfFindNumber($this->number);
			$phoneNumber = new PhoneNumber($number,$p[PhoneNumber::HEALTH_FIELD],$p[PhoneNumber::USER_ID_FIELD]);
			$phoneNumber->setHealth($callStatus);
			$phoneNumber->update();
			if($phoneNumber->status=="bad") {
				//fix bad numbers in system
				UserHelper::updateGuardianNumber($phoneNumber->userId,$petNum,$guardianNum);
			}
			return 'Updated';
		} else {
			return "No change";
		}
	}
	static public function smsCallback( WP_REST_Request $request ) {
		if (array_key_exists('To', $_POST) && array_key_exists('SmsStatus', $_POST) ) {
			//TwilioHelper::sendMsg($_POST['SmsStatus'],'7736092730');
			$number = PhoneNumber::stripPrefix($_POST['To']);
			$status = $_POST['SmsStatus'];
			return PhoneNumber::updateNumberHealth($number,$status);
		} else {
			return "Invalid query";
		}
	}
	static public function markInvalid($userId,$data) {

	}
}
class Test {
	static public function phoneNumber() 	{
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
		Test::phoneNumber();
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
			$this->invalidUser();
		} else {
			$this->createAndSend($user);
		}
	}
	private function invalidUser() {
			TwilioHelper::createConfirmation('false',"<p><b>Error: Invalid user ID provided, messages not sent!</b></p>");
			return 0;
	}
	public function createAndSend($user) {
			$data = get_metadata('user', $user->ID);
			$primary = $data['mobile_phone'][0];
			$pets = array();
			$numPets = Pets::numOfPets($data);
			for($i=1;$i<($numPets+1);$i++) {
				$pets[$i] = Pet::getPet($pet_owner_id,$i,$data);
			}
			$str = TwilioHelper::createMessage($_POST,$pets);
			$this->sendAlerts($str,$primary,$pets,$user->ID);
	}
	public function findUser($pet_owner_id) {
		$user = false;
		$query = new WP_User_Query( array( 'meta_key' => 'pet_owner_id', 'meta_value' => $pet_owner_id ) );
		if (count($query->results) == 1) {
			$user = $query->results[0];
		}
		return $user;
	}
	public function sendAlerts($str,$primary,$pets,$userId) {
		$okay = 'true';
		$primary = $this->alertPrimary($str,$primary,$userId);
		if($primary == 0) {
			$msg = "Warning: We were unable to send a message to the primary pet owner. ";
		} else {
			$msg = "Message sent to the primary pet owner. ";
		}
		$alerted = $this->alertGuardians($str,$pets,$userId);
		if($alerted->sent > 0 ) {
			$msg .= "You successfully sent ".$alerted->sent." messages to Pet Guardians. ";
		}
		if($alerted->failed > 0) {
			$msg .= "Warning: We were unable to send ".$alerted->failed." messages to Pet Guardians. ";
		}
		if ($primary == 0 && $alerted->sent == 0) {$okay = 'false';}
		TwilioHelper::createConfirmation($okay,$msg);
	}
	public function alertPrimary($str,$number,$userId) {
		try {
		   TwilioHelper::sendMsg($str,$number);
		} catch (Exception $e) {
			PhoneNumber::updateNumberHealth($phoneNumber->number,'failed'); 
			UserHelper::updatePrimaryNumber($userId,"_____");
			mail ( 'ianlowell@gmail.com' , 'Bad Number: Pet Guardian' , $e->getMessage() );
			return 0;
		}
		return 1;
	}
	public function alertGuardians($str,$pets,$userId) {
		$alerts = new StdClass;
		$alerts->sent = 0;
		$alerts->total = 0;
		foreach($pets as $p) {
			$gNum = 1;
			foreach($p->guardians as $g) {
				//check the number, if it's new, save to the db
				$phoneNumber = PhoneNumber::lookup($g->mobile_phone,$userId);
				if($g->response==='1' && $phoneNumber->health != "bad") {
					$alerts->sent++;
					$alerts->total++;
					try {
					  TwilioHelper::sendMsg($str,$phoneNumber->number);
					} catch (Exception $e) {
						$alerts->sent--;
						//mark number as bad, update user meta, send emails
						PhoneNumber::updateNumberHealth($phoneNumber->number,'failed'); 
						UserHelper::updateGuardianNumber($userId,$p->petfile,$gNum,$newNum);
						mail ( 'ianlowell@gmail.com' , 'Bad Number: Pet Guardian' , $e->getMessage() );
					}
				} else {
					//don't send to an invalid number
				}
				$gNum++;
			}
		}
		$alerts->failed = $alerts->total - $alerts->sent;
		return $alerts;
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
