<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write\Flag;

/**
 * In case the referenced association data will be deleted, the related data will be deleted too
 */
class CascadeDelete extends Flag
{
}
