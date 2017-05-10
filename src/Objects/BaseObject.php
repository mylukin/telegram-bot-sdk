<?php

namespace Telegram\Bot\Objects;

use Illuminate\Support\Collection;

/**
 * Class BaseObject.
 * @mixin Collection
 */
abstract class BaseObject extends Collection
{
    /**
     * Builds collection entity.
     *
     * @param array|mixed $data
     */
    public function __construct($data)
    {
        parent::__construct($this->getRawResult($data));
    }

    /**
     * Property relations.
     *
     * @return array
     */
    abstract public function relations();

    /**
     * Magically access collection data.
     *
     * @param $property
     *
     * @return mixed
     */
    public function __get($property)
    {
        return $this->getPropertyValue($property);
    }

    /**
     * Magically map to an object class (if exists) and return data.
     *
     * @param      $property
     * @param null $default
     *
     * @return mixed
     */
    protected function getPropertyValue($property, $default = null)
    {
        $property = snake_case($property);
        if (!$this->offsetExists($property)) {
            return value($default);
        }

        $value = $this->items[$property];

        $relations = $this->relations();
        if (isset($relations[$property])) {
            return $relations[$property]::make($value);
        }

        /** @var BaseObject $class */
        $class = 'Telegram\Bot\Objects\\'.studly_case($property);

        if (class_exists($class)) {
            return $class::make($value);
        }

        if (is_array($value)) {
            return TelegramObject::make($value);
        }

        return $value;
    }

    /**
     * Get an item from the collection by key.
     *
     * @param mixed $key
     * @param mixed $default
     *
     * @return mixed|static
     */
    public function get($key, $default = null)
    {
        $value = parent::get($key, $default);

        if (!is_null($value) && is_array($value)) {
            return $this->getPropertyValue($key, $default);
        }

        return $this->items = collect($this->all())
            ->map(function ($value, $key) use ($relations) {
                if (!$relations->has($key)) {
                    return $value;
                }

                $className = $relations->get($key);
                return new $className($value);
            })
            ->all();
    }

    /**
     * Returns raw response.
     *
     * @return array|mixed
     */
    public function getRawResponse()
    {
        return $this->items;
    }

    /**
     * Returns raw result.
     *
     * @param $data
     *
     * @return mixed
     */
    public function getRawResult($data)
    {
        return array_get($data, 'result', $data);
    }

    /**
     * Get Status of request.
     *
     * @return mixed
     */
    public function getStatus()
    {
        return array_get($this->items, 'ok', false);
    }

    /**
     * Magic method to get properties dynamically.
     *
     * @param $name
     * @param $arguments
     *
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        $action = substr($name, 0, 3);
        if ($action !== 'get') {
            return false;
        }
        $property = snake_case(substr($name, 3));
        $response = $this->get($property);

        // Map relative property to an object
        $relations = $this->relations();
        if (null != $response && isset($relations[$property])) {
            return new $relations[$property]($response);
        }

        return $response;
    }
}
