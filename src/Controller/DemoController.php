<?php

declare(strict_types=1);

namespace App\Controller;

use App\Agent\OrderDesk;
use App\Order\Order;
use App\Order\OrderRepository;
use App\Order\Payments;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Workflow\TransitionBlocker;
use Symfony\Component\Workflow\WorkflowInterface;
use WorkflowGate\TransitionGate;

final class DemoController extends AbstractController
{
    public function __construct(
        private readonly OrderRepository $orders,
        private readonly WorkflowInterface $orderStateMachine,
        private readonly TransitionGate $gate,
    ) {
    }

    #[Route('/', methods: 'GET')]
    public function page(): Response
    {
        return $this->render('demo.html.twig');
    }

    #[Route('/api/orders/{id}', methods: 'GET')]
    public function state(int $id): JsonResponse
    {
        return $this->json($this->describe($this->order($id)));
    }

    #[Route('/api/orders/{id}/ask', methods: 'POST')]
    public function ask(int $id, #[MapRequestPayload] AskRequest $request, OrderDesk $desk): JsonResponse
    {
        $run = $desk->ask($this->order($id)->id, $request->message, $request->mode);

        return $this->json(['answer' => $run['answer'], 'calls' => $run['calls']] + $this->describe($run['order']));
    }

    #[Route('/api/orders/{id}/pay', methods: 'POST')]
    public function pay(int $id, Payments $payments): JsonResponse
    {
        return $this->json($this->describe($payments->receive($this->order($id)->id)));
    }

    #[Route('/api/reset', methods: 'POST')]
    public function reset(): JsonResponse
    {
        $this->orders->reset();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    private function order(int $id): Order
    {
        return $this->orders->find($id) ?? throw new NotFoundHttpException(\sprintf('There is no order %d.', $id));
    }

    /**
     * @return array<string, mixed>
     */
    private function describe(Order $order): array
    {
        $tools = $this->gate->tools($order);
        $definition = $this->orderStateMachine->getDefinition();
        $transitions = [];

        foreach ($definition->getTransitions() as $transition) {
            $name = $transition->getName();
            $fromHere = \in_array($order->getStatus(), $transition->getFroms(), true);
            $blockers = $fromHere ? iterator_to_array($this->orderStateMachine->buildTransitionBlockerList($order, $name)) : [];

            $transitions[] = [
                'name' => $name,
                'from' => $transition->getFroms(),
                'to' => $transition->getTos(),
                'agentTool' => isset($this->gate->allTools($order)[$name.'_order']),
                'enabled' => $fromHere && [] === $blockers,
                'blockedBecause' => implode(' ', array_map(static fn (TransitionBlocker $blocker): string => $blocker->getMessage(), $blockers)),
            ];
        }

        return [
            'orders' => array_map(static fn (Order $o): array => ['id' => $o->id, 'customer' => $o->customer, 'total' => $o->total, 'status' => $o->getStatus()], $this->orders->all()),
            'order' => ['id' => $order->id, 'customer' => $order->customer, 'total' => $order->total, 'status' => $order->getStatus()],
            'places' => array_values($definition->getPlaces()),
            'transitions' => $transitions,
            'tools' => array_values(array_map(static fn ($tool): array => ['name' => $tool->name, 'description' => $tool->description], $tools)),
        ];
    }
}
