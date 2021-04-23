<?php

declare(strict_types=1);

namespace Tests\JivoChat\Validator\Constraint;

use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\Persistence\ManagerRegistry;
use Exception;
use JivoChat\Validator\Constraint\Exception\NotFound;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use JivoChat\Validator\Constraint\EntityExists;
use JivoChat\Validator\Constraint\EntityExistsValidator;
use stdClass;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

use function array_flip;

/**
 * @covers \JivoChat\Validator\Constraint\EntityExistsValidator
 */
class EntityExistsValidatorTest extends TestCase
{
    /** @var EntityExistsValidator */
    private $validator;

    /** @var MockObject|ExecutionContextInterface */
    private $context;

    protected function setUp(): void
    {
        $this->context = $this->getMockBuilder(ExecutionContextInterface::class)->getMock();

        $this->validator = new EntityExistsValidator($this->createMock(ManagerRegistry::class));
        $this->validator->initialize($this->context);
    }

    public function testValidateWithWrongConstrainThrowException(): void
    {
        $this->expectException(UnexpectedTypeException::class);
        $this->validator->validate('', new NotBlank());
    }

    public function testConstraintMustContainsEntityProperty(): void
    {
        $this->expectException(RuntimeException::class);
        $this->validator->validate('', new EntityExists());
    }

    public function testFindManagerUsingCustomPersistentManager(): void
    {
        $registry                      = $this->getRegistry();
        $registry->managers['default'] = $this->createMock(EntityManagerInterface::class);
        $registry->managers['global']  = $this->createMock(EntityManagerInterface::class);

        $validator = new EntityExistsValidator($registry);
        $validator->initialize($this->context);

        $resultRegistry = $validator->findManager($registry, 'StdClass', 'global');

        $this->assertSame($registry->getManager('global'), $resultRegistry);
    }

    /** START testing function FindManager */
    public function testFindManagerWithDefaultParameters(): void
    {
        $registry                      = $this->getRegistry();
        $registry->managers['default'] = $this->createMock(EntityManagerInterface::class);

        $validator = new EntityExistsValidator($registry);
        $validator->initialize($this->context);

        $resultRegistry = $validator->findManager($registry, 'StdClass', null);

        $this->assertSame($registry->getManager('default'), $resultRegistry);
    }

    public function testFindManagerByEntity(): void
    {
        $registry                       = $this->getRegistry();
        $registry->managers['default']  = $this->createMock(EntityManagerInterface::class);
        $registry->managers['sharding'] = $this->createManager(['NotStdEntity', 'SecondEntity']);
        $registry->managers['global']   = $this->createManager(['StdEntity', 'OtherEntity']);

        $validator = new EntityExistsValidator($registry);
        $validator->initialize($this->context);

        $resultRegistry = $validator->findManager($registry, 'StdEntity', null);

        $this->assertSame($registry->getManager('global'), $resultRegistry);
    }

    /**
     * @param array<string> $entityNameSpace
     */
    private function createManager(array $entityNameSpace): EntityManagerInterface
    {
        $nameSpaces = [];
        foreach ($entityNameSpace as $item) {
            $nameSpaces[$item] = $item;
        }
        $configurationContainsEntity = $this->createMock(Configuration::class);
        $configurationContainsEntity->method('getEntityNamespaces')
            ->willReturn($nameSpaces);

        $managerContainsEntity = $this->createMock(EntityManagerInterface::class);
        $managerContainsEntity->method('getConfiguration')->willReturn($configurationContainsEntity);

        return $managerContainsEntity;
    }

    /**
     * @return stdClass|ManagerRegistry
     */
    private function getRegistry()
    {
        return new class implements ManagerRegistry {
            /** @var mixed[] */
            public $managers = [];

            /**
             * @inheritdoc
             */
            public function getManager($name = null)
            {
                return $this->managers[$name];
            }

            /**
             * @inheritdoc
             */
            public function getManagers()
            {
                return $this->managers;
            }

            /**
             * @inheritdoc
             */
            public function getDefaultManagerName()
            {
                return 'default';
            }

            /**
             * @inheritdoc
             */
            public function getDefaultConnectionName()
            {
                return '';
            }

            /**
             * @inheritdoc
             */
            public function getConnection($name = null)
            {
                return new stdClass();
            }

            /**
             * @inheritdoc
             */
            public function getConnections()
            {
                return [];
            }

            /**
             * @inheritdoc
             */
            public function getConnectionNames()
            {
                return [];
            }

            /**
             * @inheritdoc
             */
            public function resetManager($name = null)
            {
                throw new Exception();
            }

            /**
             * @inheritdoc
             */
            public function getAliasNamespace($alias)
            {
                return '';
            }

            /**
             * @inheritdoc
             */
            public function getManagerNames()
            {
                throw new Exception();
            }

            /**
             * @inheritdoc
             */
            public function getRepository($persistentObject, $persistentManagerName = null)
            {
                throw new Exception();
            }

            /**
             * @inheritdoc
             */
            public function getManagerForClass($class)
            {
                return null;
            }
        };
    }

    /** END testing function FindManager */

    /** Start testing function GetPrimaryKeys */
    public function testGetPrimaryKeysNotFoundKeys(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getClassMetadata')->with('StdClass')->willReturn(new ClassMetadataInfo('StdClass'));

        $this->expectException(RuntimeException::class);
        $this->validator->getPrimaryKeys('StdClass', $entityManager);
    }

    public function testGetPrimaryKeysFoundKeys(): void
    {
        $metaData             = new ClassMetadataInfo('StdClass');
        $metaData->identifier = ['firstPrimaryKey', 'secondPrimaryKey'];

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getClassMetadata')->with('StdClass')->willReturn($metaData);

        $keys = $this->validator->getPrimaryKeys('StdClass', $entityManager);

        $this->assertSame(
            [
                'firstPrimaryKey'  => 'firstPrimaryKey',
                'secondPrimaryKey' => 'secondPrimaryKey',
            ],
            $keys
        );
    }

    /** END testing function GetPrimaryKeys */

    /** START testing function EntityHasPrimaryKey */
    public function testEntityHasPrimaryKeyWithNotExistsKey(): void
    {
        $primaryKeys = ['firstPrimaryKey' => 'firstPrimaryKey', 'secondPrimaryKey' => 'secondPrimaryKey'];

        $this->expectException(RuntimeException::class);
        $this->validator->entityHasPrimaryKey('notExistsKey', $primaryKeys);
    }

    public function testEntityHasPrimaryKeyWithExistingKey(): void
    {
        $primaryKeys = ['firstPrimaryKey' => 'firstPrimaryKey', 'secondPrimaryKey' => 'secondPrimaryKey'];
        $mapping     = ['entityKey' => 'customKey'];

        try {
            $this->validator->entityHasPrimaryKey('firstPrimaryKey', $primaryKeys, $mapping);
        } catch (RuntimeException $e) {
            $this->fail('firstPrimaryKey was not found in primary keys list');
        }
    }

    public function testEntityHasPrimaryKeyWithMappedExistingKey(): void
    {
        $primaryKeys = ['firstPrimaryKey' => 'firstPrimaryKey', 'secondPrimaryKey' => 'secondPrimaryKey'];
        $mapping     = ['firstPrimaryKey' => 'customKey'];

        try {
            $this->validator->entityHasPrimaryKey('customKey', $primaryKeys, $mapping);
        } catch (RuntimeException $e) {
            $this->fail('customKey was not found in primary keys list');
        }
    }

    /** END testing function EntityHasPrimaryKey */

    /** START testing function MakeCriteria */
    public function testMakeCriteriaDidNotFoundPrimaryKeyInObject(): void
    {
        $this->context->method('getPropertyName')->willReturn('key1');
        $mapping = ['key1' => 'key1', 'key2' => 'key2'];

        $this->expectException(RuntimeException::class);
        $this->validator->makeCriteria('value', $mapping);
    }

    public function testMakeCriteria(): void
    {
        $dto = new class {
            public function getKey2(): string
            {
                return 'valueKey2';
            }

            public function getCustomKey(): string
            {
                return 'valueKey3';
            }
        };
        $this->context->method('getObject')->willReturn($dto);
        $this->context->method('getPropertyName')->willReturn('key1');
        $mapping = [
            'key1' => 'key1',
            'key2' => 'key2',
            'key3' => 'customKey',
        ];

        $criteria = $this->validator->makeCriteria('valueKey1', array_flip($mapping));

        $this->assertSame(
            [
                'key1' => 'valueKey1',
                'key2' => 'valueKey2',
                'key3' => 'valueKey3',
            ],
            $criteria
        );
    }

    /** END testing function MakeCriteria */
    public function testThrowViolationWithException(): void
    {
        $constraint            = new EntityExists();
        $constraint->exception = true;

        $this->expectException(NotFound::class);
        $this->validator->throwViolation($constraint, 'value');
    }

    public function testThrowViolationWithOutException(): void
    {
        $constraint            = new EntityExists();
        $constraint->exception = false;

        $builder = $this->createMock(ConstraintViolationBuilderInterface::class);
        $builder->method('setParameter')->willReturn($builder);

        $this->context->expects($this->atLeastOnce())->method('buildViolation')->willReturn($builder);
        $this->validator->throwViolation($constraint, 'value');
    }
}
