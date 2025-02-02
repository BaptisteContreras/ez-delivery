<?php

namespace Ezdeliver\Factory;

use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorFromClassMetadata;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\BackedEnumNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;

class SfFactory
{
    private ?SerializerInterface $serializer = null;
    private ?JsonEncoder $jsonEncoder = null;
    private ?ObjectNormalizer $objectNormalizer = null;

    private ?ClassMetadataFactory $classMetadataFactory = null;
    private ?ClassDiscriminatorFromClassMetadata $classDiscriminatorFromClassMetadata = null;


    public function createSfSerializer(): SerializerInterface
    {
        if (null === $this->serializer) {
            $this->serializer = new Serializer([
                new ArrayDenormalizer(),
                new DateTimeNormalizer(),
                new BackedEnumNormalizer(),
                $this->createObjectNormalizer(),
            ], [$this->createJsonEncoder()]);
        }

        return $this->serializer;
    }

    private function createJsonEncoder(): JsonEncoder
    {
        return $this->jsonEncoder ??= new JsonEncoder();
    }

    private function createObjectNormalizer(): ObjectNormalizer
    {
        return $this->objectNormalizer ??= new ObjectNormalizer(
            $this->createMetadataFactory(),
            null,
            null,
            new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]),
            $this->createClassDiscriminatorFromClassMetadata(),

        );
    }

    private function createMetadataFactory(): ClassMetadataFactory
    {
        return $this->classMetadataFactory ??= new ClassMetadataFactory(new AttributeLoader());
    }

    private function createClassDiscriminatorFromClassMetadata(): ClassDiscriminatorFromClassMetadata
    {
        return $this->classDiscriminatorFromClassMetadata ??= new ClassDiscriminatorFromClassMetadata($this->createMetadataFactory());
    }
}