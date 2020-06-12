<?php

namespace BestIt\ODMParamConverterBundle\Converter;

use BestIt\CommercetoolsODM\DocumentManagerInterface;
use BestIt\CommercetoolsODM\Exception\ResponseException;
use BestIt\CommercetoolsODM\Mapping\ClassMetadataInterface;
use Commercetools\Core\Model\Common\JsonObject;
use Doctrine\Common\Persistence\Mapping\MappingException;
use InvalidArgumentException;
use LogicException;
use ReflectionException;
use ReflectionMethod;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Request\ParamConverter\ParamConverterInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class ODMConverter
 *
 * @author Michel Chowanski <chowanski@bestit-online.de>
 * @package BestIt\ODMParamConverterBundle\Converter
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
class ODMConverter implements ParamConverterInterface
{
    /**
     * The document manager
     *
     * @var DocumentManagerInterface
     */
    private $documentManager;

    /**
     * ODMConverter constructor.
     *
     * @param DocumentManagerInterface $documentManager
     */
    public function __construct(DocumentManagerInterface $documentManager)
    {
        $this->documentManager = $documentManager;
    }

    /**
     * {@inheritdoc}
     *
     * @throws \LogicException
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    public function apply(Request $request, ParamConverter $configuration)
    {
        $name = $configuration->getName();
        $class = $configuration->getClass();
        $options = $configuration->getOptions();

        try {
            // Find by identifier?
            if (($object = $this->findByIdentifier($request, $options, $class)) === false) {
                // Find by criteria
                if (($object = $this->findByCriteria($request, $options, $class)) === false) {
                    if ($configuration->isOptional()) {
                        $object = null;
                    } else {
                        throw new LogicException(
                            'Unable to guess how to get a Commercetools instance from the request information.'
                        );
                    }
                }
            }
        } catch (ResponseException $exception) {
            throw new NotFoundHttpException(
                sprintf('%s object not found.', $class),
                $exception,
                $exception->getCode()
            );
        }

        if ($object === null && $configuration->isOptional() === false) {
            throw new NotFoundHttpException(sprintf('%s object not found.', $class));
        }

        $request->attributes->set($name, $object);

        return true;
    }

    /**
     * Find by an identifier
     *
     * @param Request $request
     * @param array $options
     *
     * @param string $class
     *
     * @return mixed
     */
    private function findByCriteria(Request $request, array $options, string $class)
    {
        // No mapping? Just take all variables from request
        if (!array_key_exists('mapping', $options)) {
            $keys = $request->attributes->keys();
            $options['mapping'] = $keys ? array_combine($keys, $keys) : [];
        }

        // If a specific id has been defined in the options and there is no corresponding attribute
        // return false in order to avoid a fallback to the id which might be of another object
        if (isset($options['id']) && $request->attributes->get($options['id']) === null) {
            return false;
        }

        $mapMethodBySignature = $options['map_method_signature'] ?? false;

        // Resolve mapping
        $resolvedMapping = $this->resolveMapping($request, $options, $class);
        $repository = $this->documentManager->getRepository($class);

        // Use custom method
        if ($method = ($options['repository_method'] ?? null)) {
            if ($mapMethodBySignature === true) {
                return $this->findDataByMapMethodSignature($repository, $method, $resolvedMapping);
            }

            return $repository->{$method}($resolvedMapping);
        }

        return $repository->findOneBy($resolvedMapping);
    }

    /**
     * Find by an identifier
     *
     * @param Request $request
     * @param array $options
     * @param string $class
     *
     * @return mixed
     */
    private function findByIdentifier(Request $request, array $options, string $class)
    {
        $primaryKey = $options['id'] ?? 'id';
        $mapMethodBySignature = $options['map_method_signature'] ?? false;

        // If we don't have any key ... return false
        if (!$request->attributes->has($primaryKey)) {
            return false;
        }

        $value = $request->attributes->get($primaryKey);
        $repository = $this->documentManager->getRepository($class);
        if ($method = ($options['repository_method'] ?? null)) {
            if ($mapMethodBySignature === true) {
                return $this->findDataByMapMethodSignature($repository, $method, ['id' => $value]);
            }

            return $this->{$method}(['id' => $value]);
        }

        return $repository->findOneBy(['id' => $value]);
    }

    /**
     * Find data by map method signature
     *
     * @param $repository
     * @param $repositoryMethod
     * @param $criteria
     *
     * @return mixed
     * @throws InvalidArgumentException
     * @throws ReflectionException
     */
    private function findDataByMapMethodSignature($repository, $repositoryMethod, $criteria)
    {
        $arguments = [];

        $ref = new ReflectionMethod($repository, $repositoryMethod);
        foreach ($ref->getParameters() as $parameter) {
            if (array_key_exists($parameter->name, $criteria)) {
                $arguments[] = $criteria[$parameter->name];
            } elseif ($parameter->isDefaultValueAvailable()) {
                $arguments[] = $parameter->getDefaultValue();
            } else {
                throw new InvalidArgumentException(
                    sprintf(
                        'Repository method "%s::%s" requires that you provide a value for the "$%s" argument.',
                        get_class($repository),
                        $repositoryMethod,
                        $parameter->name
                    )
                );
            }
        }

        return $ref->invokeArgs($repository, $arguments);
    }

    /**
     * Get the known field for the given class name
     *
     * @param string $className The name of the class
     *
     * @return array
     */
    private function getKnownFieldsFromClass(string $className):array
    {
        $knownFields = [];
        $meta = $this->documentManager->getClassMetadata($className);
        if (($jsonObject = $meta->getReflectionClass()->newInstance()) instanceof JsonObject) {
            $knownFields = array_keys($jsonObject->fieldDefinitions());
        }

        return $knownFields;
    }

    /**
     * Resolve the mapping for the class from the given request
     *
     * @param Request $request The current request
     * @param array $options The options for the resolve process
     * @param string $className The name of the class
     *
     * @return array
     */
    private function resolveMapping(Request $request, array $options, string $className):array
    {
        // Resolve mapping
        $resolvedMapping = [];
        $knownFields = $this->getKnownFieldsFromClass($className);
        $mapMethodBySignature = $options['map_method_signature'] ?? false;

        foreach ($options['mapping'] as $attribute => $field) {
            if ($mapMethodBySignature === true || in_array($field, $knownFields, true)) {
                $resolvedMapping[$field] = $request->attributes->get($attribute);
            }
        }

        return $resolvedMapping;
    }

    /**
     * {@inheritdoc}
     */
    public function supports(ParamConverter $configuration)
    {
        $isSupported = false;

        if ($class = $configuration->getClass()) {
            try {
                $meta = $this->documentManager->getClassMetadata($class);

                $isSupported = !(!$meta instanceof ClassMetadataInterface || strlen($meta->getRepository()) <= 0);
            } catch (MappingException $mappingException) {
                // do nothing.
            }
        }

        return $isSupported;
    }
}
