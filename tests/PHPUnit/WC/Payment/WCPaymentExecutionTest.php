<?php
/**
 * Created by PhpStorm.
 * User: biont
 * Date: 05.12.16
 * Time: 17:30
 */

namespace WCPayPalPlus\WC\Payment;

use Brain\Monkey\WP\Actions;
use MonkeryTestCase\BrainMonkeyWpTestCase;
use PayPal\Api\Payment;
use PayPal\Exception\PayPalConnectionException;
use PayPal\Rest\ApiContext;

class WCPaymentExecutionTest extends BrainMonkeyWpTestCase {

	/**
	 *
	 */
	public function test_execute() {

		$payment = \Mockery::mock( Payment::class );
		$payment->shouldReceive( 'execute' )
		        ->once();
		$data = \Mockery::mock( PaymentExecutionData::class );
		$data->shouldReceive( 'get_payment' )
		     ->once()
		     ->andReturn( $payment );
		$data->shouldReceive( 'get_payment_execution' )
		     ->once()
		     ->andReturn( true );
		$data->shouldReceive( 'get_context' )
		     ->once()
		     ->andReturn( true );
		$success = \Mockery::mock( PaymentExecutionSuccess::class );
		$success->shouldReceive( 'register' );
		$success->shouldReceive( 'execute' )
		        ->once();

		$testee = new WCPaymentExecution( $data, [ $success ] );
		$result = $testee->execute();
		$this->assertTrue( $result );
	}

	public function test_execute_throws_exception() {

		$exception = \Mockery::mock( PayPalConnectionException::class );
		$payment   = \Mockery::mock( Payment::class );
		$payment->shouldReceive( 'execute' )
		        ->once()
		        ->andThrow( $exception );
		$data = \Mockery::mock( PaymentExecutionData::class );
		$data->shouldReceive( 'get_payment' )
		     ->once()
		     ->andReturn( $payment );
		$data->shouldReceive( 'get_payment_execution' )
		     ->once()
		     ->andReturn( true );
		$data->shouldReceive( 'get_context' )
		     ->once()
		     ->andReturn( true );
		$success = \Mockery::mock( PaymentExecutionSuccess::class );
		$success->shouldReceive( 'register' );
		$success->shouldNotReceive( 'execute' );

		Actions::expectFired( 'paypal_plus_plugin_log_exception' )
		       ->once()
		       ->with( 'payment_execution_exception', $exception );

		$testee = new WCPaymentExecution( $data, [ $success ] );
		$result = $testee->execute();
		$this->assertFalse( $result );
	}
}
