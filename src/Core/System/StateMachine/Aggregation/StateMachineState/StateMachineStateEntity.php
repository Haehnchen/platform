<?php declare(strict_types=1);

namespace Shopware\Core\System\StateMachine\Aggregation\StateMachineState;

use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionCollection;
use Shopware\Core\System\StateMachine\StateMachineCollection;
use Shopware\Core\System\StateMachine\StateMachineEntity;

class StateMachineStateEntity extends Entity
{
    use EntityIdTrait;
    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $technicalName;

    /**
     * @var string
     */
    protected $stateMachineId;

    /**
     * @var StateMachineEntity
     */
    protected $stateMachine;

    /**
     * @var StateMachineTransitionCollection|null
     */
    protected $fromStateMachineTransitions;

    /**
     * @var StateMachineTransitionCollection|null
     */
    protected $toStateMachineTransitions;

    /**
     * @var StateMachineCollection|null
     */
    protected $initialStateStateMachines;

    /**
     * @var StateMachineStateTranslationCollection
     */
    protected $translations;

    /**
     * @var \DateTime
     */
    protected $createdAt;

    /**
     * @var \DateTime|null
     */
    protected $updatedAt;

    /**
     * @var OrderCollection|null
     */
    protected $orders;

    /**
     * @var OrderTransactionCollection|null
     */
    protected $orderTransactions;

    /**
     * @var OrderDeliveryCollection|null
     */
    protected $orderDeliveries;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getStateMachineId(): string
    {
        return $this->stateMachineId;
    }

    public function setStateMachineId(string $stateMachineId): void
    {
        $this->stateMachineId = $stateMachineId;
    }

    public function getStateMachine(): StateMachineEntity
    {
        return $this->stateMachine;
    }

    public function setStateMachine(StateMachineEntity $stateMachine): void
    {
        $this->stateMachine = $stateMachine;
    }

    public function getFromStateMachineTransitions(): ?StateMachineTransitionCollection
    {
        return $this->fromStateMachineTransitions;
    }

    public function setFromStateMachineTransitions(StateMachineTransitionCollection $fromStateMachineTransitions): void
    {
        $this->fromStateMachineTransitions = $fromStateMachineTransitions;
    }

    public function getToStateMachineTransitions(): ?StateMachineTransitionCollection
    {
        return $this->toStateMachineTransitions;
    }

    public function setToStateMachineTransitions(StateMachineTransitionCollection $toStateMachineTransitions): void
    {
        $this->toStateMachineTransitions = $toStateMachineTransitions;
    }

    public function getInitialStateStateMachines(): ?StateMachineCollection
    {
        return $this->initialStateStateMachines;
    }

    public function setInitialStateStateMachines(StateMachineCollection $initialStateStateMachines): void
    {
        $this->initialStateStateMachines = $initialStateStateMachines;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function getTranslations(): StateMachineStateTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(StateMachineStateTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }

    public function getTechnicalName(): string
    {
        return $this->technicalName;
    }

    public function setTechnicalName(string $technicalName): void
    {
        $this->technicalName = $technicalName;
    }

    public function getOrders(): ?OrderCollection
    {
        return $this->orders;
    }

    public function setOrders(OrderCollection $orders): void
    {
        $this->orders = $orders;
    }

    public function getOrderTransactions(): ?OrderTransactionCollection
    {
        return $this->orderTransactions;
    }

    public function setOrderTransactions(OrderTransactionCollection $orderTransactions): void
    {
        $this->orderTransactions = $orderTransactions;
    }

    public function getOrderDeliveries(): ?OrderDeliveryCollection
    {
        return $this->orderDeliveries;
    }

    public function setOrderDeliveries(OrderDeliveryCollection $orderDeliveries): void
    {
        $this->orderDeliveries = $orderDeliveries;
    }
}
