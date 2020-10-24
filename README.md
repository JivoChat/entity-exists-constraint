# Symfony validator for checking if Entity Exist

[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)

This validator verifies that an Entity actually exists in a database. 
You can use it in different DTO objects. For instance in request object or service dto's

```php
namespace App\Requests;

use JivoChat\Validator\Constraint\EntityExists;
use Symfony\Component\Validator\Constraints as Assert;

final class RequestDto
{
    /**
     * @Assert\NotBlank
     * @EntityExists(entity="Entity\User")
     *
     * @var int User's id property
     * In this case validator will found User primary key. If key has the same name as the parameter (userId), 
     * validator will try to found record in database (where userId = $userId). 
     * If primary key has different name, exception will be thrown
     */
    private $userId;

    /**
     * @Assert\NotBlank
     * @EntityExists(entity="Entity\Post", mapping={"postId": "messageId"}, exception=true)
     *
     * @var int
     * Let's assume that Post has composite primary key userId, postId. Because of the mapping, the validator knows that
     * it must take the value from $messageId and set it to the postId parameter in the query.
     * For the userId parameter it will try to find parameter with the same name or with name from mapping 
     * in the dto object. If parameter will be found, validator will take value. if not, exception will be thrown.

     * Parameter "exception" tells what to do if entity was not found. If it is true, exception will be thrown
     */
    private $messageId;

     /**
     * @Assert\NotBlank
     * @EntityExists(entity="Entity\Transaction", mapping={"ownerId": "userId"}, persistentManager="global")
     *
     * @var int
     * Let's assume that Transaction has primary key transactionId. In this case mapping says to add parameter ownerId 
     * in query and take value from dto parameter $userId (where transactionId = $transactionId AND ownerId = $userId)
     * 
     * Parameter "persistentManager" tells which entity manager using in this case. By default using manager which 
     * contains entity or default manager.
     */
    private $transactionId;

    public  function getUserId(): int
    {
        return $this->userId;
    }

    public  function getMessageId(): int
    {
        return $this->messageId;
    }

    public  function getTransactionId(): int
    {
        return $this->transactionId;
    }
}
```

You can use default EntityExists constrain from package or make your own to change default parameters.

```php
class EntityExists extends Constraint
{
    /** @var string */
    public $message = 'Entity "%entity%" with parameter "%value%" does not exist.';
    // Violation message. You can use parameters %entity%, %value%, %parameters%

    /** @var string */
    public $entity = '';

    /** @var array<string, string> */
    public $mapping = [];

    /** @var null|string */
    public $persistentManager = null;

    /** @var bool */
    public $exception = false;
    // If it is true, exception will be thrown. It is good then you want to show 404 page

    public function throwException(): void
    {
        throw NotFound::entity($this->entity);
    }
}
```

## Full example
```php
 $dto = new RequestDto();
 $dto->setUserId($request->request->get('user_id'));
 $dto->setMessageId($request->request->get('message_id'));
 $dto->setTransactionId($request->request->get('transaction_id'));

 $violationList = $this->validator->validate($requestDto);
 if ($violationList->count()) {
    ...
 }
````

## Install

```console
composer require jivochat/entity-exists-constraint
```

Then register the services with:

```yaml
services:
  JivoChat\Validator\Constraint\EntityExistsValidator:
      arguments: [ '@doctrine' ]
      tags: [ 'validator.constraint_validator' ]
```