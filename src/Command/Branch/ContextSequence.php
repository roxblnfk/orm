<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\ORM\Command\Branch;

use Spiral\ORM\Command\ContextCarrierInterface;
use Spiral\ORM\Exception\CommandException;

/**
 * Wraps the sequence with commands and provides an ability to mock access to the primary command.
 */
class ContextSequence extends Sequence implements ContextCarrierInterface
{
    /** @var ContextCarrierInterface */
    protected $primary;

    /**
     * Add primary command to the sequence.
     *
     * @param ContextCarrierInterface $command
     */
    public function addPrimary(ContextCarrierInterface $command)
    {
        $this->addCommand($command);
        $this->primary = $command;
    }

    /**
     * @return ContextCarrierInterface
     */
    public function getPrimary(): ContextCarrierInterface
    {
        if (empty($this->primary)) {
            throw new CommandException("Primary sequence command is not set");
        }

        return $this->primary;
    }

    /**
     * {@inheritdoc}
     */
    public function waitContext(string $key, bool $required = true)
    {
        return $this->getPrimary()->waitContext($key, $required);
    }

    /**
     * {@inheritdoc}
     */
    public function getContext(): array
    {
        return $this->getPrimary()->getContext();
    }

    /**
     * @inheritdoc
     */
    public function register(
        string $key,
        $value,
        bool $update = false,
        int $stream = self::DATA
    ) {
        $this->getPrimary()->register($key, $value, $update, $stream);
    }
}