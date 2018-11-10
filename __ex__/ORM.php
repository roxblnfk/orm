<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J2
 */

namespace Spiral\ORM2;

use Interop\Container\ContainerInterface;
use Spiral\Core\Component;
use Spiral\Core\Container;
use Spiral\Core\Container\SingletonInterface;
use Spiral\Core\FactoryInterface;
use Spiral\Core\MemoryInterface;
use Spiral\Core\NullMemory;
use Spiral\Database\DatabaseManager;
use Spiral\Database\Entities\Database;
use Spiral\Database\Entities\Table;
use Spiral\ORM\Configs\RelationsConfig;
use Spiral\ORM\Entities\RecordSelector;
use Spiral\ORM\Entities\RecordSource;
use Spiral\ORM\Exceptions\ORMException;
use Spiral\ORM\Exceptions\SchemaException;
use Spiral\ORM\Schemas\LocatorInterface;
use Spiral\ORM\Schemas\NullLocator;
use Spiral\ORM\Schemas\SchemaBuilder;

class ORM2 extends Component implements ORMInterface, SingletonInterface
{
    /**
     * @var LocatorInterface
     */
    private $locator;

    /**
     * Already created instantiators.
     *
     * @invisible
     * @var InstantiatorInterface[]
     */
    private $instantiators = [];

    /**
     * ORM schema.
     *
     * @invisible
     * @var array
     */
    private $schema = [];

    /**
     * @var DatabaseManager
     */
    protected $manager;

    /**
     * @var RelationsConfig
     */
    protected $config;

    /**
     * @invisible
     * @var MemoryInterface
     */
    protected $memory;

    /**
     * Container defines working scope for all Documents and DocumentEntities.
     *
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @param DatabaseManager         $manager
     * @param RelationsConfig         $config
     * @param LocatorInterface|null   $locator
     * @param EntityMap|null          $map
     * @param MemoryInterface|null    $memory
     * @param ContainerInterface|null $container
     */
    public function __construct(
        DatabaseManager $manager,
        RelationsConfig $config,
        //Following arguments can be resolved automatically
        LocatorInterface $locator = null,
        EntityMap $map = null,
        MemoryInterface $memory = null,
        ContainerInterface $container = null
    ) {
        $this->manager = $manager;
        $this->config = $config;

        //If null is passed = no caching is expected
        $this->map = $map;

        $this->locator = $locator ?? new NullLocator();
        $this->memory = $memory ?? new NullMemory();
        $this->container = $container ?? new Container();

        //Loading schema from memory (if any)
        $this->schema = $this->loadSchema();
    }

    /**
     * {@inheritdoc}
     */
    public function hasMap(): bool
    {
        return !empty($this->map);
    }

    /**
     * {@inheritdoc}
     */
    public function getMap(): EntityMap
    {
        if (empty($this->map)) {
            throw new ORMException("Attempts to access entity map in mapless mode");
        }

        return $this->map;
    }

    /**
     * {@inheritdoc}
     */
    public function withMap(EntityMap $map = null): ORMInterface
    {
        $orm = clone $this;
        $orm->map = $map;

        return $orm;
    }

    /**
     * Create instance of ORM SchemaBuilder.
     *
     * @param bool $locate Set to true to automatically locate available records and record sources
     *                     sources in a project files (based on tokenizer scope).
     *
     * @return SchemaBuilder
     *
     * @throws SchemaException
     */
    public function schemaBuilder(bool $locate = true): SchemaBuilder
    {
        /**
         * @var SchemaBuilder $builder
         */
        $builder = $this->getFactory()->make(SchemaBuilder::class, ['manager' => $this->manager]);

        if ($locate) {
            foreach ($this->locator->locateSchemas() as $schema) {
                $builder->addSchema($schema);
            }

            foreach ($this->locator->locateSources() as $class => $source) {
                $builder->addSource($class, $source);
            }
        }

        return $builder;
    }

    /**
     * Specify behaviour schema for ORM to be used. Attention, you have to call renderSchema()
     * prior to passing builder into this method.
     *
     * @param SchemaBuilder $builder
     * @param bool          $remember Set to true to remember packed schema in memory.
     */
    public function setSchema(SchemaBuilder $builder, bool $remember = false)
    {
        $this->schema = $builder->packSchema();

        if ($remember) {
            $this->memory->saveData(static::MEMORY, $this->schema);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function define(string $class, int $property)
    {
        if (empty($this->schema)) {
            $this->setSchema($this->schemaBuilder()->renderSchema(), true);
        }

        //Check value
        if (!isset($this->schema[$class])) {
            throw new ORMException("Undefined ORM schema item '{$class}', make sure schema is updated");
        }

        if (!array_key_exists($property, $this->schema[$class])) {
            throw new ORMException("Undefined ORM schema property '{$class}'.'{$property}'");
        }

        return $this->schema[$class][$property];
    }

    /**
     * Get source (selection repository) for specific entity class.
     *
     * @param string $class
     *
     * @return RecordSource
     */
    public function source(string $class): RecordSource
    {
        $source = $this->define($class, self::R_SOURCE_CLASS);

        if (empty($source)) {
            //Let's use default source
            $source = RecordSource::class;
        }

        $handles = $source::RECORD;
        if (empty($handles)) {
            //Force class to be handled
            $handles = $class;
        }

        return $this->getFactory()->make($source, [
            'class' => $handles,
            'orm'   => $this
        ]);
    }

    /**
     * Load packed schema from memory.
     *
     * @return array
     */
    protected function loadSchema(): array
    {
        return (array)$this->memory->loadData(static::MEMORY);
    }

    /**
     * Get ODM specific factory.
     *
     * @return FactoryInterface
     */
    protected function getFactory(): FactoryInterface
    {
        if ($this->container instanceof FactoryInterface) {
            return $this->container;
        }

        return $this->container->get(FactoryInterface::class);
    }
}
