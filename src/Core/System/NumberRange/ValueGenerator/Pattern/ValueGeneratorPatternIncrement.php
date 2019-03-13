<?php declare(strict_types=1);

namespace Shopware\Core\System\NumberRange\ValueGenerator\Pattern;

use Shopware\Core\System\NumberRange\NumberRangeEntity;
use Shopware\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\IncrementStorageInterface;

class ValueGeneratorPatternIncrement implements ValueGeneratorPatternInterface
{
    /**
     * @var IncrementStorageInterface
     */
    protected $incrementConnector;

    public function __construct(IncrementStorageInterface $incrementConnector)
    {
        $this->incrementConnector = $incrementConnector;
    }

    public function getPatternId(): string
    {
        return 'n';
    }

    public function resolve(NumberRangeEntity $configuration, ?array $args = null, ?bool $preview = false): string
    {
        if ($preview === true) {
            return $this->incrementConnector->getNext($configuration, $incrementBy = 1);
        }

        return $this->incrementConnector->pullState($configuration, $incrementBy = 1);
    }
}