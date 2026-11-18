<?php

declare(strict_types=1);

namespace App\Tests\App;

use App\Order\Order;
use App\Order\OrderRepository;
use App\Order\Payments;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use WorkflowGate\TransitionBlocked;
use WorkflowGate\TransitionGate;

/**
 * The rules of the demo shop, checked against the real workflow configuration.
 */
final class OrderDeskRulesTest extends KernelTestCase
{
    private OrderRepository $orders;
    private TransitionGate $gate;

    protected function setUp(): void
    {
        $this->orders = $this->service(OrderRepository::class);
        $this->gate = $this->service(TransitionGate::class);
        $this->orders->reset();
    }

    protected function tearDown(): void
    {
        $this->orders->reset();
        parent::tearDown();
    }

    public function testAnUnpaidOrderCanOnlyBeCancelledByAnAgent(): void
    {
        self::assertSame(['cancel_order'], array_keys($this->gate->tools($this->order(1001))));
    }

    public function testPayingIsNeverAnAgentTool(): void
    {
        self::assertArrayNotHasKey('pay_order', $this->gate->allTools($this->order(1001)));
    }

    public function testAPaymentFromOutsideMakesShippingATool(): void
    {
        $this->service(Payments::class)->receive(1001);

        self::assertSame(['ship_order', 'refund_order'], array_keys($this->gate->tools($this->order(1001))));
        self::assertSame('paid', $this->order(1001)->getStatus());
    }

    public function testAnAgentCannotRefundMoreThanTheLimit(): void
    {
        $expensive = $this->order(1003);

        self::assertSame(['ship_order'], array_keys($this->gate->tools($expensive)));

        $this->expectException(TransitionBlocked::class);
        $this->expectExceptionMessage('Refunds above 500 EUR need a human.');

        $this->gate->apply($expensive, 'refund');
    }

    public function testAnAppliedTransitionIsStored(): void
    {
        $this->gate->apply($this->order(1002), 'ship');

        self::assertSame('shipped', $this->order(1002)->getStatus());
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function service(string $class): object
    {
        $service = self::getContainer()->get($class);
        self::assertInstanceOf($class, $service);

        return $service;
    }

    private function order(int $id): Order
    {
        return $this->orders->find($id) ?? self::fail(\sprintf('Order %d is missing.', $id));
    }
}
