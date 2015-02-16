<?php

namespace spec\Isolate\UnitOfWork;

use Isolate\UnitOfWork\CommandBus\SilentBus;
use Isolate\UnitOfWork\Entity\ChangeBuilder;
use Isolate\UnitOfWork\Entity\Comparer;
use Isolate\UnitOfWork\Entity\Definition;
use Isolate\UnitOfWork\Entity\ClassName;
use Isolate\UnitOfWork\Entity\Definition\Identity;
use Isolate\UnitOfWork\Entity\Definition\Property;
use Isolate\UnitOfWork\Entity\Identifier\Symfony\PropertyAccessorIdentifier;
use Isolate\UnitOfWork\Exception\InvalidArgumentException;
use Isolate\UnitOfWork\Exception\RuntimeException;
use Isolate\UnitOfWork\EntityStates;
use Isolate\UnitOfWork\Object\InMemoryRegistry;
use Isolate\UnitOfWork\Object\RecoveryPoint;
use Isolate\UnitOfWork\Object\SnapshotMaker\Adapter\DeepCopy\SnapshotMaker;
use Isolate\UnitOfWork\Tests\Double\EntityFake;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class UnitOfWorkSpec extends ObjectBehavior
{
    function let()
    {
        $definition = new Definition(
            new ClassName(EntityFake::getClassName()),
            new Identity("id")
        );
        $definition->setObserved([new Property("firstName"), new Property("lastName"), new Property("items")]);

        $definitions = new Definition\Repository\InMemory([$definition]);

        $identifier = new PropertyAccessorIdentifier($definitions);
        $registry = new InMemoryRegistry(new SnapshotMaker(), new RecoveryPoint());
        $commandBus = new SilentBus($definitions);
        $this->beConstructedWith(
            $registry,
            $identifier,
            new ChangeBuilder($definitions, $identifier),
            new Comparer($definitions),
            $commandBus
        );
    }

    function it_throw_exception_during_non_object_registration()
    {
        $this->shouldThrow(new InvalidArgumentException("Only objects can be registered in Unit of Work."))
            ->during("register", ["Coduo"]);
    }

    function it_throw_exception_during_non_entity_registration()
    {
        $this->shouldThrow(new InvalidArgumentException("Only entities can be registered in Unit of Work."))
            ->during("register", [new \DateTime()]);
    }

    function it_should_throw_exception_when_checking_unregistered_entity_state()
    {
        $this->shouldThrow(new RuntimeException("Object need to be registered first in the Unit of Work."))
            ->during("getEntityState", [new \DateTime]);
    }

    function it_tells_when_entity_was_registered()
    {
        $entity = new EntityFake();
        $this->register($entity);
        $this->isRegistered($entity)->shouldReturn(true);
    }

    function it_should_return_new_state_when_registered_entity_was_not_persisted()
    {
        $entity = new EntityFake();

        $this->register($entity);

        $this->getEntityState($entity)->shouldReturn(EntityStates::NEW_ENTITY);
    }

    function it_should_return_persisted_entity_state_when_registered_object_was_persisted()
    {
        $entity = new EntityFake(1);

        $this->register($entity);

        $this->getEntityState($entity)->shouldReturn(EntityStates::PERSISTED_ENTITY);
    }

    function it_should_return_edited_state_when_entity_was_modified_after_registration()
    {
        $entity = new EntityFake(1);

        $this->register($entity);

        $entity->changeFirstName("new name");

        $this->getEntityState($entity)->shouldReturn(EntityStates::EDITED_ENTITY);
    }

    function it_should_throw_exception_on_removing_not_persisted_entity()
    {
        $entity = new EntityFake();
        $this->shouldThrow(new RuntimeException("Unit of Work can't remove not persisted entities."))
            ->during('remove', [$entity]);
    }

    function it_return_removed_state_for_not_registered_but_persisted_entity()
    {
        $entity = new EntityFake(1);
        $this->remove($entity);
        $this->getEntityState($entity)->shouldReturn(EntityStates::REMOVED_ENTITY);
    }
}
