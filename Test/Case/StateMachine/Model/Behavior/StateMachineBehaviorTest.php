<?php
App::uses('StateMachineBehavior', 'StateMachine.Model/Behavior');

class BaseVehicle extends CakeTestModel {

	public $useTable = 'vehicles';

	public $actsAs = array('StateMachine.StateMachine');

	public $initialState = 'parked';

	public $transitions = array(
		'ignite' => array(
			'parked' => 'idling',
			'stalled' => 'stalled'
		),
		'park' => array(
			'idling' => 'parked',
			'first_gear' => 'parked'
		),
		'shift_up' => array(
			'idling' => 'first_gear',
			'first_gear' => 'second_gear',
			'second_gear' => 'third_gear'
		),
		'shift_down' => array(
			'first_gear' => 'idling',
			'second_gear' => 'first_gear',
			'third_gear' => 'second_gear'
		),
		'crash' => array(
			'first_gear' => 'stalled',
			'second_gear' => 'stalled',
			'third_gear' => 'stalled'
		),
		'repair' => array(
			'stalled' => 'parked'
		),
		'idle' => array(
			'first_gear' => 'idling'
		),
		'turn_off' => array(
			'all' => 'parked'
		),
		'baz' => array()
	);
}

class Vehicle extends BaseVehicle {

	public function onStateChange($newState) {
	}

	public function onStateIdling($newState) {
	}

	public function onBeforeTransition($currentState, $previousState, $transition) {
	}

	public function onAfterTransition($currentState, $previousState, $transition) {
	}

	public function onBeforeIgnite($currentState, $previousState, $transition) {
	}

}

class RulesVehicle extends BaseVehicle {

	public $transitionRules = array(
		'hardwire' => array(
			'role' => array('thief'),
		),
		'ignite' => array(
			'role' => array('driver'),
			'depends' => 'has_key'
		),
		'park'	=> array(
			'role' => array('driver', 'thief'),
			'depends' => 'available_parking'
		),
		'repair' => array(
			'role' => array('mechanic'),
			'depends' => 'has_tools'
		)
	);

	public function __construct($id = false, $table = null, $ds = null) {
		$this->transitions += array(
			'hardwire' => array(
				'parked' => 'idling',
				'stalled' => 'stalled'
			)
		);

		parent::__construct($id, $table, $ds);
	}

	public function hasKey($role) {
		if ($role == 'driver') {
			return true;
		}

		return false;
	}

	public function availableParking($role) {
		return $role == 'thief';
	}

}

class StateMachineBehaviorTest extends CakeTestCase {

	public $fixtures = array(
		'plugin.state_machine.vehicle'
	);

	public $Vehicle;

	public $StateMachine;

	public function setUp() {
		parent::setUp();

		$this->Vehicle = new Vehicle(1);
		$this->StateMachine = $this->Vehicle->Behaviors->StateMachine;
	}

	public function testInitialState() {
		$this->assertEquals("parked", $this->Vehicle->getCurrentState());
		$this->assertEquals('parked', $this->Vehicle->getStates('turn_off'));
	}

	public function testIsMethods() {
		$this->assertEquals($this->Vehicle->isParked(), $this->Vehicle->is('parked'));
		$this->assertEquals($this->Vehicle->isIdling(), $this->Vehicle->is('idling'));
		$this->assertEquals($this->Vehicle->isStalled(), $this->Vehicle->is('stalled'));
		$this->assertEquals($this->Vehicle->isIdling(), $this->Vehicle->is('idling'));

		$this->assertEquals($this->Vehicle->canShiftUp(), $this->Vehicle->can('shift_up'));
		$this->assertFalse($this->Vehicle->canShiftUp());

		$this->assertTrue($this->Vehicle->canIgnite());
		$this->Vehicle->ignite();
		$this->assertEquals("idling", $this->Vehicle->getCurrentState());

		$this->assertTrue($this->Vehicle->canShiftUp());
		$this->assertFalse($this->Vehicle->canShiftDown());

		$this->assertTrue($this->Vehicle->isIdling());
		$this->assertFalse($this->Vehicle->canCrash());
		$this->Vehicle->shiftUp();
		$this->Vehicle->crash();
		$this->assertEquals("stalled", $this->Vehicle->getCurrentState());
		$this->assertTrue($this->Vehicle->isStalled());
		$this->Vehicle->repair();
		$this->assertTrue($this->Vehicle->isParked());
	}

	public function testOnMethods() {
		$this->Vehicle->onIgnite('before', function($currentState, $previousState, $transition) {
			$this->assertEquals("parked", $currentState);
			$this->assertNull($previousState);
			$this->assertEquals("ignite", $transition);
		});

		$this->Vehicle->on('ignite', 'after', function($currentState, $previousState, $transition) {
			$this->assertEquals("idling", $currentState);
			$this->assertEquals("parked", $previousState);
			$this->assertEquals("ignite", $transition);
		});

		$this->Vehicle->ignite();
	}

	public function testBadMethodCall() {
		$this->setExpectedException('PDOException');
		$this->Vehicle->isFoobar();
	}

	public function whenParked() {
		$this->assertEquals('parked', $this->Vehicle->getCurrentState());
	}

	public function testWhenMethods() {
		$this->Vehicle->whenStalled(function() {
			$this->assertEquals("stalled", $this->Vehicle->getCurrentState());
		});

		$this->Vehicle->when('parked', array($this, 'whenParked'));

		$this->Vehicle->ignite();
		$this->Vehicle->shiftUp();
		$this->Vehicle->crash();
		$this->Vehicle->repair();
	}

	public function testBubble() {
		$this->Vehicle->on('ignite', 'before', function() {
			$this->assertEquals("parked", $this->Vehicle->getCurrentState());
		}, false);

		$this->Vehicle->on('transition', 'before', function() {
			// this should never be called
			$this->assertTrue(false);
		});

		$this->Vehicle->ignite();
	}

	public function testInvalidTransition() {
		$this->assertFalse($this->Vehicle->getStates('foobar'));
		$this->assertFalse($this->Vehicle->getStates('baz'));
		$this->assertFalse($this->Vehicle->baz());
	}

	public function testVehicleTitle() {
		$this->Vehicle = new Vehicle(3);

		$this->assertEquals("Opel Astra", $this->Vehicle->field('title'));
		$this->assertEquals("idling", $this->Vehicle->getCurrentState());
		$this->Vehicle->shiftUp();
		$this->assertEquals("first_gear", $this->Vehicle->getCurrentState());

		$this->Vehicle = new Vehicle(4);
		$this->assertEquals("Nissan Leaf", $this->Vehicle->field('title'));
		$this->assertEquals("stalled", $this->Vehicle->getCurrentState());
		$this->assertTrue($this->Vehicle->canRepair());
		$this->assertTrue($this->Vehicle->repair());
		$this->assertEquals("parked", $this->Vehicle->getCurrentState());
	}

	public function testCreateVehicle() {
		$this->Vehicle->create();
		$this->Vehicle->save(array(
			'Vehicle' => array(
				'title' => 'Toybota'
			)
		));
		$this->Vehicle->id = $this->Vehicle->getLastInsertID();
		$this->assertEquals($this->Vehicle->initialState, $this->Vehicle->getCurrentState());
	}

	public function testToDot() {
		$this->Vehicle->toDot();
	}

	public function testCallable() {
		$this->Vehicle->addMethod('whatIsMyName', function(Model $model, $method, $name) {
			return $model->alias . '-' . $method . '-' . $name;
		});

		$this->assertEquals("Vehicle-whatIsMyName-Toybota", $this->Vehicle->whatIsMyName("Toybota"));
	}

	public function testExistingCallable() {
		$this->Vehicle->addMethod('foobar', function() {
		});

		$this->setExpectedException('InvalidArgumentException');
		$this->Vehicle->addMethod('foobar', function() {
		});
	}

	public function testUnhandled() {
		$this->setExpectedException('PDOException');
		$this->assertEquals(array("unhandled"), $this->Vehicle->handleMethodCall("foobar"));
	}

	public function testInvalidOnStateChange() {
		$this->Vehicle = new BaseVehicle(1);
		$this->Vehicle->ignite();
	}

	public function testOnStateChange() {
		$this->Vehicle = $this->getMock('Vehicle', array(
			'onStateChange', 'onStateIdling', 'onBeforeTransition', 'onAfterTransition'));
		$this->Vehicle->expects($this->once())->method('onBeforeTransition');
		$this->Vehicle->expects($this->once())->method('onAfterTransition');
		$this->Vehicle->expects($this->once())->method('onStateChange');
		$this->Vehicle->expects($this->once())->method('onStateIdling');

		$this->assertTrue($this->Vehicle->ignite());
	}

	public function testRules() {
		$this->Vehicle = new RulesVehicle(1);

		$this->assertTrue($this->Vehicle->canIgnite('driver'));
		$this->assertFalse($this->Vehicle->canIgnite('thief'));
		$this->assertTrue($this->Vehicle->canHardwire('thief'));
		$this->assertFalse($this->Vehicle->canHardwire('driver'));

		$this->Vehicle->ignite('driver');

		$this->assertFalse($this->Vehicle->canPark('driver'));
		$this->assertTrue($this->Vehicle->canPark('thief'));
	}

	public function testRuleWithCallback() {
		$this->Vehicle = new RulesVehicle(1);
		$this->Vehicle->ignite('driver');
		$this->Vehicle->shiftUp();
		$this->Vehicle->crash();

		$this->Vehicle->addMethod('hasTools', function($role) {
			return $role == 'mechanic';
		});

		$this->assertTrue($this->Vehicle->canRepair('mechanic'));
		$this->assertTrue($this->Vehicle->repair('mechanic'));
	}

	public function testInvalidRules() {
		$this->setExpectedException('InvalidArgumentException');

		$this->Vehicle = new RulesVehicle(1);
		$this->Vehicle->ignite();
	}

	public function testWrongRole() {
		$this->Vehicle = new RulesVehicle(1);
		$this->assertFalse($this->Vehicle->ignite('thief'));
	}

	public function tearDown() {
		parent::tearDown();
		unset($this->Vehicle, $this->StateMachine);
	}
}
