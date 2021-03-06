<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Provisioning;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Util\Random;

class UserProvisioner
{
    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function provision(string $username, string $password = null, array $additionalData = []): string
    {
        if ($this->userExists($username)) {
            throw new \RuntimeException(sprintf('User with username "%s" already exists.', $username));
        }

        $password = $password ?? Random::getAlphanumericString(8);

        $userPayload = [
            'id' => Uuid::uuid4()->getBytes(),
            'name' => $additionalData['name'] ?? $username,
            'email' => $additionalData['email'] ?? 'info@shopware.com',
            'username' => $username,
            'password' => password_hash($password, PASSWORD_BCRYPT),
            'locale_id' => Uuid::fromHexToBytes(Defaults::LOCALE_SYSTEM),
            'active' => true,
            'created_at' => date(Defaults::DATE_FORMAT),
        ];

        $this->connection->insert('user', $userPayload);

        return $password;
    }

    private function userExists(string $username): bool
    {
        $builder = $this->connection->createQueryBuilder();

        return $builder->select(1)
            ->from('user')
            ->where('username = :username')
            ->setParameter('username', $username)
            ->execute()
            ->rowCount() > 0;
    }
}
