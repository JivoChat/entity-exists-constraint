<?php

/**
 * stub_class ExampleDto
 * {
 *     //@EntityExists(entity="Entity\User")
 *     private $userId;
 *     В данном случае валидатор попытается найти первичные ключи сущности User и если название первичного
 *     ключа совпадает с названием параметра ($userId), сделает запрос в базу с попыткой найти запись.
 *
 *     //@EntityExists(entity="Entity\Agent", mapping={"agentId": "accountId"}, exception=true)
 *     private $accountId;
 *     Предположим что Agent содержит составной ключ userId, agentId. Валидатор по мапингу определит что значение
 *     для agentId надо взять из параметра $accountId. Для userId валидатор попытается найти параметр $userId в Dto.
 *     Если параметр найден, то значение будет взято из него. Если нет, то будет выкинуто исключение что не найдены
 *     значения для ключа.
 *     Параметр exception=true говорит о том, что в случае если сущность не найдена в базе будет выброшено исключение.
 *
 *     //@EntityExists(entity="Entity\Transaction", mapping={"userId": "userId"}, persistentManager="global")
 *     private $transactionId;
 *     Предположим что Transaction содержит первичный ключ transactionId. Маппинг в данном случае указыват на то, что в
 *     дополнении к первичному ключу в условие выборки надо добавить поле siteId и значение взять из параметра $userId.
 *     persistentManager - говорит о том что надо использовать менеджер под названием global. Если он не указан,
 *     по умолчанию будет браться менеджер к которому привязана сущность.
 * }
 */

declare(strict_types=1);

namespace JivoChat\Validator\Constraint;

use JivoChat\Validator\Constraint\Exception\NotFound;
use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class EntityExists extends Constraint
{
    public string $message = 'Entity "%entity%" with parameter "%value%" does not exist.';

    public string $entity = '';

    /** @var array<string, string> */
    public $mapping = [];

    public ?string $persistentManager = null;

    public bool $exception = false;

    public function throwException(): void
    {
        throw NotFound::entity($this->entity);
    }
}
