<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\PhpGenerator;

use Nette;
use Nette\Utils\Strings;


/**
 * Class/Interface/Trait description.
 *
 * @property Method[] $methods
 * @property Property[] $properties
 */
final class ClassType
{
	use Nette\SmartObject;
	use Traits\CommentAware;

	public const
		TYPE_CLASS = 'class',
		TYPE_INTERFACE = 'interface',
		TYPE_TRAIT = 'trait';

	/** @var PhpNamespace|null */
	private $namespace;

	/** @var string|null */
	private $name;

	/** @var string  class|interface|trait */
	private $type = 'class';

	/** @var bool */
	private $final = false;

	/** @var bool */
	private $abstract = false;

	/** @var string|string[] */
	private $extends = [];

	/** @var string[] */
	private $implements = [];

	/** @var array[] */
	private $traits = [];

	/** @var Constant[] name => Constant */
	private $consts = [];

	/** @var Property[] name => Property */
	private $properties = [];

	/** @var Method[] name => Method */
	private $methods = [];


	/**
	 * @param  string|object  $class
	 * @return static
	 */
	public static function from($class): self
	{
		return (new Factory)->fromClassReflection(new \ReflectionClass($class));
	}


	public function __construct(string $name = null, PhpNamespace $namespace = null)
	{
		$this->setName($name);
		$this->namespace = $namespace;
	}


	public function __toString(): string
	{
		$traits = [];
		foreach ($this->traits as $trait => $resolutions) {
			$traits[] = 'use ' . ($this->namespace ? $this->namespace->unresolveName($trait) : $trait)
				. ($resolutions ? " {\n\t" . implode(";\n\t", $resolutions) . ";\n}" : ';');
		}

		$consts = [];
		foreach ($this->consts as $const) {
			$consts[] = Helpers::formatDocComment((string) $const->getComment())
				. ($const->getVisibility() ? $const->getVisibility() . ' ' : '')
				. 'const ' . $const->getName() . ' = ' . Helpers::dump($const->getValue()) . ';';
		}

		$properties = [];
		foreach ($this->properties as $property) {
			$properties[] = Helpers::formatDocComment((string) $property->getComment())
				. ($property->getVisibility() ?: 'public') . ($property->isStatic() ? ' static' : '') . ' $' . $property->getName()
				. ($property->getValue() === null ? '' : ' = ' . Helpers::dump($property->getValue()))
				. ';';
		}

		$mapper = function (array $arr) {
			return $this->namespace ? array_map([$this->namespace, 'unresolveName'], $arr) : $arr;
		};

		return Strings::normalize(
			Helpers::formatDocComment($this->comment . "\n")
			. ($this->abstract ? 'abstract ' : '')
			. ($this->final ? 'final ' : '')
			. ($this->name ? "$this->type $this->name " : '')
			. ($this->extends ? 'extends ' . implode(', ', $mapper((array) $this->extends)) . ' ' : '')
			. ($this->implements ? 'implements ' . implode(', ', $mapper($this->implements)) . ' ' : '')
			. ($this->name ? "\n" : '') . "{\n"
			. Strings::indent(
				($this->traits ? implode("\n", $traits) . "\n\n" : '')
				. ($this->consts ? implode("\n", $consts) . "\n\n" : '')
				. ($this->properties ? implode("\n\n", $properties) . "\n\n\n" : '')
				. ($this->methods ? implode("\n\n\n", $this->methods) . "\n" : ''), 1)
			. '}'
		) . ($this->name ? "\n" : '');
	}


	public function getNamespace(): ?PhpNamespace
	{
		return $this->namespace;
	}


	/**
	 * @return static
	 */
	public function setName(?string $name): self
	{
		if ($name !== null && !Helpers::isIdentifier($name)) {
			throw new Nette\InvalidArgumentException("Value '$name' is not valid class name.");
		}
		$this->name = $name;
		return $this;
	}


	public function getName(): ?string
	{
		return $this->name;
	}


	/**
	 * @return static
	 */
	public function setType(string $type): self
	{
		if (!in_array($type, ['class', 'interface', 'trait'], true)) {
			throw new Nette\InvalidArgumentException('Argument must be class|interface|trait.');
		}
		$this->type = $type;
		return $this;
	}


	public function getType(): string
	{
		return $this->type;
	}


	/**
	 * @return static
	 */
	public function setFinal(bool $state = true): self
	{
		$this->final = $state;
		return $this;
	}


	public function isFinal(): bool
	{
		return $this->final;
	}


	/**
	 * @return static
	 */
	public function setAbstract(bool $state = true): self
	{
		$this->abstract = $state;
		return $this;
	}


	public function isAbstract(): bool
	{
		return $this->abstract;
	}


	/**
	 * @param  string|string[]  $names
	 * @return static
	 */
	public function setExtends($names): self
	{
		if (!is_string($names) && !is_array($names)) {
			throw new Nette\InvalidArgumentException('Argument must be string or string[].');
		}
		$this->validate((array) $names);
		$this->extends = $names;
		return $this;
	}


	/**
	 * @return string|string[]
	 */
	public function getExtends()
	{
		return $this->extends;
	}


	/**
	 * @return static
	 */
	public function addExtend(string $name): self
	{
		$this->validate([$name]);
		$this->extends = (array) $this->extends;
		$this->extends[] = $name;
		return $this;
	}


	/**
	 * @param  string[]  $names
	 * @return static
	 */
	public function setImplements(array $names): self
	{
		$this->validate($names);
		$this->implements = $names;
		return $this;
	}


	/**
	 * @return string[]
	 */
	public function getImplements(): array
	{
		return $this->implements;
	}


	/**
	 * @return static
	 */
	public function addImplement(string $name): self
	{
		$this->validate([$name]);
		$this->implements[] = $name;
		return $this;
	}


	/**
	 * @param  string[]  $names
	 * @return static
	 */
	public function setTraits(array $names): self
	{
		$this->validate($names);
		$this->traits = array_fill_keys($names, []);
		return $this;
	}


	/**
	 * @return string[]
	 */
	public function getTraits(): array
	{
		return array_keys($this->traits);
	}


	/**
	 * @return static
	 */
	public function addTrait(string $name, array $resolutions = []): self
	{
		$this->validate([$name]);
		$this->traits[$name] = $resolutions;
		return $this;
	}


	/**
	 * @param  Constant[]|mixed[]  $consts
	 * @return static
	 */
	public function setConstants(array $consts): self
	{
		$this->consts = [];
		foreach ($consts as $k => $v) {
			$const = $v instanceof Constant ? $v : (new Constant($k))->setValue($v);
			$this->consts[$const->getName()] = $const;
		}
		return $this;
	}


	/**
	 * @return Constant[]
	 */
	public function getConstants(): array
	{
		return $this->consts;
	}


	public function addConstant(string $name, $value): Constant
	{
		return $this->consts[$name] = (new Constant($name))->setValue($value);
	}


	/**
	 * @param  Property[]  $props
	 * @return static
	 */
	public function setProperties(array $props): self
	{
		$this->properties = [];
		foreach ($props as $v) {
			if (!$v instanceof Property) {
				throw new Nette\InvalidArgumentException('Argument must be Nette\PhpGenerator\Property[].');
			}
			$this->properties[$v->getName()] = $v;
		}
		return $this;
	}


	/**
	 * @return Property[]
	 */
	public function getProperties(): array
	{
		return $this->properties;
	}


	public function getProperty(string $name): Property
	{
		if (!isset($this->properties[$name])) {
			throw new Nette\InvalidArgumentException("Property '$name' not found.");
		}
		return $this->properties[$name];
	}


	/**
	 * @param  string  $name  without $
	 */
	public function addProperty(string $name, $value = null): Property
	{
		return $this->properties[$name] = (new Property($name))->setValue($value);
	}


	/**
	 * @param  Method[]  $methods
	 * @return static
	 */
	public function setMethods(array $methods): self
	{
		$this->methods = [];
		foreach ($methods as $v) {
			if (!$v instanceof Method) {
				throw new Nette\InvalidArgumentException('Argument must be Nette\PhpGenerator\Method[].');
			}
			$this->methods[$v->getName()] = $v->setNamespace($this->namespace);
		}
		return $this;
	}


	/**
	 * @return Method[]
	 */
	public function getMethods(): array
	{
		return $this->methods;
	}


	public function getMethod(string $name): Method
	{
		if (!isset($this->methods[$name])) {
			throw new Nette\InvalidArgumentException("Method '$name' not found.");
		}
		return $this->methods[$name];
	}


	public function addMethod(string $name): Method
	{
		$method = (new Method($name))->setNamespace($this->namespace);
		if ($this->type === 'interface') {
			$method->setBody(null);
		} else {
			$method->setVisibility('public');
		}
		return $this->methods[$name] = $method;
	}


	private function validate(array $names): void
	{
		foreach ($names as $name) {
			if (!Helpers::isNamespaceIdentifier($name, true)) {
				throw new Nette\InvalidArgumentException("Value '$name' is not valid class name.");
			}
		}
	}
}
