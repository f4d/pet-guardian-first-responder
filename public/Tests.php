<?

class TestPrimary {
	public $pet_owner_id,$user;
	public function __construct() {
		$this->setup();
		echo $this->invalidUser();
		echo $this->validUser();
		echo $this->sendMsg();
		$this->cleanup();
	}
	public function setup() {
	}
	public function invalidUser() {
		$this->pet_owner_id = '000';
		$this->user = UserHelper::findUser($this->pet_owner_id);
		if($this->user===false) {return "Caught invalid pet id. PASS.<br>";}
		return "invalidUser test failed.";
	}
	public function validUser() {
		$this->pet_owner_id = '9669739645';
		$this->user = UserHelper::findUser($this->pet_owner_id);
		print_r( get_metadata('user', $this->user->ID));
		if($this->user->ID==115) {return "<br>Found user 115 by pet ID. PASS.<br>";}
		return "invalidUser test failed.";
	}
	public function sendMsg() {
		TwilioHelper::sendMsg('Ahoy, mate!','7736092730');
		return "Sending message, check text to verify!<br>";
	}

	public function cleanup() {
		//
	}
}

class TestPhoneNumber {
	public function __construct() {
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

class TestPet {
	public function __construct() {
		$this->setup();
		echo($this->testPetFileUrls());
		//$this->cleanup().' ';
	}
	public function setup() {
		$this->petOwnerId = '9541487788';
		$this->user = UserHelper::findUser($this->petOwnerId);
		$this->meta = get_metadata('user', $this->user->ID);
		//update_user_meta( $this->user->ID, 'how_many_pets_owned', '1' );

	}
	public function testPetfileUrls() {
		$msgs = '';
		$pets = array();
		$numPets = Pet::numOfPets($this->meta);
		echo " how many pets? $numPets ";
		for($i=1;$i<($numPets+1);$i++) {
			$pets[$i] = Pet::getPet($this->petOwnerId,$i,$this->meta);
			$m = TwilioHelper::petfileUrl($pets[$i]);	
			print_r($m);	
			$msgs .= $m;	
		}
		return $msgs;
	}
}
class TestUserHelper {
	public function __construct() {
		$this->setup();
		echo $this->testGuardianMobileKey().'.<br>';
		echo $this->testUpdateGuardianNumber().'<br>';
		echo $this->testUpdatePrimaryNumber().'<br>';
		echo $this->testUpdateNumbers().'<br>';
		$this->cleanup().' ';
	}
	public function setup() {
		$test = UserHelper::getGuardianNumber('115','1','1');
		print_r($test); 
	}
	public function testGuardianMobileKey(){
		echo UserHelper::guardianMobileKey('1','1');
		return 0;
	}
	public function testUpdateGuardianNumber(){
		echo UserHelper::updateGuardianNumber('115','1','1','5555555555');
		return 0;
	}
	public function testUpdatePrimaryNumber(){
		return 0;
	}
	public function testUpdateNumbers(){
		return 0;
	}
	public function cleanup() {
		echo UserHelper::updateGuardianNumber('115','1','1','(773) 609-2730');
		//
	}
}
