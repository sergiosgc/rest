<?php
namespace sergiosgc\rest;

class REST {
    protected static function filterOutUIWidgetNoneFields($descriptions, $values) {
        foreach($descriptions as $key => $description) {
            if (isset($description['ui:widget']) && $description['ui:widget'] == 'none') unset($values[$key]);
        }
        return $values;
    }
    protected static function applyFieldmap(array $target, array $map) : array {
        return \sergiosgc\ArrayAdapter::from($target)->reduceAssociative(function ($value, $key) use ($map) {
            return [ isset($map[$key]) ? $map[$key] : $key, $value ];
        })->toArray();
    }
    public static function get($class, $requestFieldMap = []) {
        if (!isset(class_implements($class)['sergiosgc\crud\Describable'])) throw new Exception("$class must implement interface sergiosgc\crud\Describable");
        $values = static::applyFieldmap($_REQUEST, $requestFieldMap);
        $values = \sergiosgc\crud\Normalizer::normalizeValues($class::describeFields(), $values);
        $keys = call_user_func([ $class, 'dbKeyFields']);
        $keyValues = array_map(
            function ($key) use ($values) { return array_key_exists($key, $values) ? $values[$key] : null; },
            $keys);
        $whereString = sprintf('(%s) = (%s)',
            implode(', ', array_map(
                function ($key) { return sprintf('"%s"', $key); },
                $keys)),
            implode(', ', array_map(
                function ($key) { return '?'; },
                $keys))
        );
        $dbReadArgs = array_merge(
            [ $whereString ],
            $keyValues);
        $result = call_user_func_array( [ $class, 'dbRead' ], $dbReadArgs );
        if (!is_object($result)) throw new NotFoundException();
        return $result;
    }
    public static function delete($class, $requestFieldMap = []) {
        if (!isset(class_implements($class)['sergiosgc\crud\Describable'])) throw new Exception("$class must implement interface sergiosgc\crud\Describable");
        $values = static::applyFieldmap($_REQUEST, $requestFieldMap);
        $values = \sergiosgc\crud\Normalizer::normalizeValues($class::describeFields(), $values);
        $keys = call_user_func([ $class, 'dbKeyFields']);
        $keyValues = array_map(
            function ($key) use ($values) { return array_key_exists($key, $values) ? $values[$key] : null; },
            $keys);
        $whereString = sprintf('(%s) = (%s)',
            implode(', ', array_map(
                function ($key) { return sprintf('"%s"', $key); },
                $keys)),
            implode(', ', array_map(
                function ($key) { return '?'; },
                $keys))
        );
        $dbReadArgs = array_merge(
            [ $whereString ],
            $keyValues);
        $result = call_user_func_array( [ $class, 'dbRead' ], $dbReadArgs );
        if (!is_object($result)) {
            if (class_exists('\sergiosgc\router\Exception_HTTP_404')) throw new \sergiosgc\router\Exception_HTTP_404();
            throw new NotFoundException();
        }
        $result->dbDelete();
        return $result;
    }
    public static function put($class, $requestFieldMap = [], $changeCallback = null) {
        if (!isset(class_implements($class)['sergiosgc\crud\Describable'])) throw new Exception("$class must implement interface sergiosgc\crud\Describable");
        $values = \sergiosgc\crud\Normalizer::normalizeValues($class::describeFields(), 
            static::filterOutUIWidgetNoneFields($class::describeFields(),
                static::applyFieldmap($_REQUEST, $requestFieldMap)
            )
        );
        $validationErrors = \sergiosgc\crud\Validator::validateValues($class::describeFields(), $values, $class);
        if ($validationErrors) throw new ValidationFailedException('Field validation failed', 0, null, $validationErrors, $values);
        $keys = call_user_func([ $class, 'dbKeyFields']);
        $keyValues = array_map(
            function ($key) use ($values) { return array_key_exists($key, $values) ? $values[$key] : null; },
            $keys);
        $whereString = sprintf('(%s) = (%s)',
            implode(', ', array_map(
                function ($key) { return sprintf('"%s"', $key); },
                $keys)),
            implode(', ', array_map(
                function ($key) { return '?'; },
                $keys))
        );
        $dbReadArgs = array_merge(
            [ $whereString ],
            $keyValues);
        $result = call_user_func_array( [ $class, 'dbRead' ], $dbReadArgs );
        if (!is_object($result)) {
            if (class_exists('\sergiosgc\router\Exception_HTTP_404')) throw new \sergiosgc\router\Exception_HTTP_404();
            throw new NotFoundException();
        }
        $changes = $result->setDescribedFields($values);
        if (!is_null($changeCallback)) call_user_func($changeCallback, $changes);
        $result->dbUpdate();
        return $result;
    }
    public static function post($class, $requestFieldMap = []) {
        if (!isset(class_implements($class)['sergiosgc\crud\Describable'])) throw new Exception("$class must implement interface sergiosgc\crud\Describable");
        if (!is_array($tvars ?? null)) $tvars = [];
        $values = \sergiosgc\crud\Normalizer::normalizeValues($class::describeFields(), 
            static::filterOutUIWidgetNoneFields($class::describeFields(),
                static::applyFieldmap($_REQUEST, $requestFieldMap)
            )
        );
        foreach (call_user_func([ $class, 'dbKeyFields']) as $key) if (array_key_exists($key, $values) && ($values[$key] === 0 || $values[$key] === "")) unset($values[$key]);
        $validationErrors = \sergiosgc\crud\Validator::validateValues($class::describeFields(), $values, $class);
        if ($validationErrors) throw new ValidationFailedException('Field validation failed', 0, null, $validationErrors, $values);
        $result = new $class;
        $result->setDescribedFields($values);
        $result->dbCreate();
        return $result;
    }
    public static function getCollection($class, $searchFields = null, $searchArgument = 'q', $sortArgument = 'sort', $pageArgument = 'page', $pageSizeArgument = 'pagesize') {
        if (!isset(class_implements($class)['sergiosgc\crud\Describable'])) throw new Exception("$class must implement interface sergiosgc\crud\Describable");
        if (!is_array($tvars ?? null)) $tvars = [];
        $request = $_REQUEST;
        if (is_array($searchFields) && !array_key_exists($searchArgument, $request)) $request[$searchArgument] = '';
        if (!array_key_exists($sortArgument, $request)) {
            $request[$sortArgument] = sprintf('%s,ASC', call_user_func([ $class, 'dbKeyFields'])[0]);
        }
        if (array_key_exists($pageArgument, $request) && !array_key_exists($pageSizeArgument, $request)) $request[$pageSizeArgument] = 20;

        if (0 === preg_match('_^[a-z]+,(?:ASC|DESC)$_', $request[$sortArgument])) throw new Exception('Invalid sort argument: ' . $request[$sortArgument]);
        if (array_key_exists($pageArgument, $request)) $request[$pageArgument] = (int) $request[$pageArgument];
        if (array_key_exists($pageSizeArgument, $request)) $request[$pageSizeArgument] = (int) $request[$pageSizeArgument];

        list($sortColumn, $sortDir) = explode(',', $request[$sortArgument], 2);
        $sortDir = $sortDir == 'DESC' ? 'DESC' : 'ASC';
        if (is_array($searchFields) && array_key_exists($searchArgument, $request) && $request[$searchArgument] != '') {
            $filter = implode(' OR ', array_map(function($field) { return sprintf('"%s"::text ILIKE (\'%%\' || ? || \'%%\')', $field); }, $searchFields));
            $filterArgs = array_map(function($key) use ($request, $searchArgument) { return $request[$searchArgument]; }, $searchFields);
        } else {
            $filter = null;
            $filterArgs = [];
        }

        $extraQueryArgs = func_get_args();
        for ($i=0; $i<6;$i++) array_shift($extraQueryArgs);
        if (count($extraQueryArgs)) {
            $_query = array_shift($extraQueryArgs);
            $filter = $filter ? sprintf('(%s) AND (%s)', $filter, $_query) : $_query;
            $filterArgs = array_merge($filterArgs, $extraQueryArgs);
        }

        if (array_key_exists($pageArgument, $request)) {
            $dbReadPagedArgs = array_merge( [ $sortColumn, $sortDir, $filter, $request[$pageArgument], $request[$pageSizeArgument] ], $filterArgs);
            list($result, $pageCount) = call_user_func_array( [ $class, 'dbReadPaged' ], $dbReadPagedArgs );
        } else {
            $dbReadAllArgs = array_merge([ $sortColumn, $sortDir, $filter ], $filterArgs);
            $pageCount = 1;
            $result = \call_user_func_array( [ $class, 'dbReadAll' ], $dbReadAllArgs );
        }
        return [ $pageCount, $result ];
    }
}
