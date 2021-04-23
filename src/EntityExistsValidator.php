<?php

declare(strict_types=1);

namespace JivoChat\Validator\Constraint;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use JivoChat\Validator\Constraint\Exception\NotFound;
use RuntimeException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

use function array_flip;
use function array_merge;
use function call_user_func_array;
use function class_exists;
use function in_array;
use function method_exists;
use function sprintf;
use function ucfirst;

class EntityExistsValidator extends ConstraintValidator
{
    private ManagerRegistry $registry;

    public function __construct(ManagerRegistry $registry)
    {
        $this->registry = $registry;
    }

    /**
     * @param Constraint|EntityExists $constraint
     *
     * @throws NotFound
     *
     * @inheritdoc
     */
    public function validate($value, Constraint $constraint)
    {
        if (! $constraint instanceof EntityExists) {
            throw new UnexpectedTypeException($constraint, EntityExists::class);
        }

        if (! $constraint->entity || ! class_exists($constraint->entity)) {
            throw new RuntimeException('Entity value is not filled or entity class not exists');
        }

        if ($value === null || $value === '') {
            return;
        }

        $entityManager = $this->findManager(
            $this->registry,
            $constraint->entity,
            $constraint->persistentManager
        );
        $primaryKeys   = $this->getPrimaryKeys($constraint->entity, $entityManager);

        $this->entityHasPrimaryKey($this->context->getPropertyName(), $primaryKeys, $constraint->mapping);

        $mapping    = array_merge($primaryKeys, $constraint->mapping);
        $criteria   = $this->makeCriteria($value, array_flip($mapping));
        /** @var EntityRepository $repository */
        $repository = $entityManager->getRepository($constraint->entity);

        $count = $repository->count($criteria);

        if ($count > 1) {
            throw new RuntimeException(sprintf('More than one entity %s found by key %s', $constraint->entity, $value));
        }

        if ($count === 1) {
            return;
        }

        $this->throwViolation($constraint, $value, $mapping);
    }

    /**
     * @param EntityManagerInterface|ObjectManager $entityManager
     *
     * @return array<string, string>
     */
    public function getPrimaryKeys(string $entityClass, ObjectManager $entityManager): array
    {
        $primaryKeys = $entityManager->getClassMetadata($entityClass)->getIdentifierFieldNames();

        if (! $primaryKeys) {
            throw new RuntimeException(sprintf('Primary key of entity "%s" not found', $entityClass));
        }

        $normalizedPrimaryKeys = [];
        foreach ($primaryKeys as $primaryKey) {
            $normalizedPrimaryKeys[$primaryKey] = $primaryKey;
        }

        return $normalizedPrimaryKeys;
    }

    public function findManager(
        ManagerRegistry $registry,
        string $entityClass,
        ?string $persistentManager = null
    ): ObjectManager {
        if ($persistentManager !== null) {
            return $registry->getManager($persistentManager);
        }

        /** @var EntityManagerInterface $manager */
        foreach ($registry->getManagers() as $name => $manager) {
            if ($name === $registry->getDefaultManagerName()) {
                continue;
            }

            $namespaces = $manager->getConfiguration()->getEntityNamespaces();
            if (! isset($namespaces[$entityClass])) {
                continue;
            }

            return $manager;
        }

        return $registry->getManager($registry->getDefaultManagerName());
    }

    /**
     * @param mixed                 $value   parameter from dto object
     * @param array<string, string> $mapping key - parameter name in dto, value - parameter name in entity
     *
     * @return array<string, mixed>
     */
    public function makeCriteria($value, array $mapping): array
    {
        $entityParamName            = $mapping[$this->context->getPropertyName()];
        $criteria[$entityParamName] = $value;
        foreach ($mapping as $dtoParameter => $entityParameter) {
            if ($dtoParameter === $this->context->getPropertyName()) {
                continue;
            }

            $getter = 'get' . ucfirst($dtoParameter);
            if (! method_exists($this->context->getObject(), $getter)) {
                throw new RuntimeException(
                    sprintf('Parameter for key %s:%s was not found', $dtoParameter, $entityParameter)
                );
            }

            $criteria[$entityParameter] = call_user_func_array([$this->context->getObject(), $getter], []);
        }

        return $criteria;
    }

    /**
     * @param array<string, string> $primaryKeys
     * @param array<string, string> $mapping
     */
    public function entityHasPrimaryKey(string $propertyName, array $primaryKeys, array $mapping = []): void
    {
        $property = $propertyName;
        if ($mapping) {
            $flippedMapping = array_flip($mapping);
            $property       = $flippedMapping[$propertyName] ?? $property;
        }

        if (! in_array($property, $primaryKeys, true)) {
            throw new RuntimeException(
                sprintf('Entity does not have primary key %s', $propertyName)
            );
        }
    }

    /**
     * @param mixed $value
     * @param mixed[] $mapping
     *
     * @throws NotFound
     */
    public function throwViolation(EntityExists $constraint, $value, array $mapping = []): void
    {
        if ($constraint->exception === true) {
            $constraint->throwException();
        }

        $this->context->buildViolation($constraint->message)
            ->setParameter('%entity%', $constraint->entity)
            ->setParameter('%value%', $value)
            ->setParameter('%parameters%', implode(', ', $mapping))
            ->addViolation();
    }
}
