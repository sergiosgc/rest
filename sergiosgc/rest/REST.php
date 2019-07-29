<?php
namespace sergiosgc\rest;

class REST {
    public function get($class, $tvars = []) {
        if (!isset(class_implements($class)['sergiosgc\crud\Describable'])) throw new Exception("$class must implement interface sergiosgc\crud\Describable");
        $keys = call_user_func([ $class, 'dbKeyFields']);
        $keyValues = array_map(
            function ($key) { return array_key_exists($key, $values) ? $values[$key] : null; },
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
        $tvars['result'] = [ 'success' => true, 'data' => call_user_func_array( [ $class, 'dbRead' ], $dbReadArgs ) ];
    }
    public function put($class, $tvars = []) {
        if (!isset(class_implements($class)['sergiosgc\crud\Describable'])) throw new Exception("$class must implement interface sergiosgc\crud\Describable");
        if (!is_array($tvars)) $tvars = [];
        $values = $_REQUEST;
        $values = \sergiosgc\crud\Normalizer::normalizeValues($class::describeFields(), $values);
        $validationErrors = \sergiosgc\crud\Validator::validateValues($class::describeFields(), $values, $class);
        if ($validationErrors) {
            $tvars['result'] = [ 'success' => false, 'data' => [
                'validation-errors' => $validationErrors,
                'form-data' => $values
            ]];
        } else {
            $keys = call_user_func([ $class, 'dbKeyFields']);
            $keyValues = array_map(
                function ($key) { return array_key_exists($key, $values) ? $values[$key] : null; },
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
            $tvars['result'] = [ 'success' => true, 'data' => call_user_func_array( [ $class, 'dbRead' ], $dbReadArgs ) ];
            if (is_null($tvars['result']['data']) && class_exists('\sergiosgc\router\Exception_HTTP_404')) throw new \sergiosgc\router\Exception_HTTP_404();
            if (is_null($tvars['result']['data'])) $tvars['result']['success'] = false;
            $tvars['result']['data']->setDescribedFields($values);
            $tvars['result']['data']->dbUpdate();
        }
        return $tvars;
    }
    public function post($class, $tvars = []) {
        if (!isset(class_implements($class)['sergiosgc\crud\Describable'])) throw new Exception("$class must implement interface sergiosgc\crud\Describable");
        if (!is_array($tvars)) $tvars = [];
        $values = $_REQUEST;
        $values = \sergiosgc\crud\Normalizer::normalizeValues($class::describeFields(), $values);
        foreach (call_user_func([ $class, 'dbKeyFields']) as $key) if (array_key_exists($key, $values) && ($values[$key] === 0 || $values[$key] === "")) unset($values[$key]);
        $validationErrors = \sergiosgc\crud\Validator::validateValues($class::describeFields(), $values, $class);
        if ($validationErrors) {
            $tvars['result'] = [ 'success' => false, 'data' => [
                'validation-errors' => $validationErrors,
                'form-data' => $values
            ]];
        } else {
            $tvars['result'] = [ 'success' => true, 'data' => new $class ];
            $tvars['result']['data']->setDescribedFields($values);
            $tvars['result']['data']->dbCreate();
        }
        return $tvars;
    }
    public function getCollection($class, $searchFields = null, $searchArgument = 'q', $sortArgument = 'sort', $pageArgument = 'page', $pageSizeArgument = 'pagesize') {
        if (!isset(class_implements($class)['sergiosgc\crud\Describable'])) throw new Exception("$class must implement interface sergiosgc\crud\Describable");
        if (!is_array($tvars)) $tvars = [];
        $request = $_REQUEST;
        if (is_array($searchFields) && !array_key_exists($searchArgument, $request)) $request[$searchArgument] = '';
        if (!array_key_exists($sortArgument, $request)) {
            $request[$sortArgument] = sprintf('%s,ASC', call_user_func([ $class, 'dbKeyFields'])[0]);
        }
        if (array_key_exists($pageArgument, $request) && !array_key_exists($pageSizeArgument, $request)) $request[$pageSizeArgument] = 20;

        if (0 === preg_match('_^[a-z]+,(?:ASC|DESC)$_', $request[$sortArgument])) throw new Exception('Invalid sort argument: ' . $request[$sortArgument]);
        if (array_key_exists($pageArgumnet, $request)) $request[$pageArgument] = (int) $request[$pageArgument];
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

        if (array_key_exists($pageArgument, $request)) {
            $dbReadPagedArgs = array_merge( [ $sortColumn, $sortDir, $filter, $request[$pageArgument], $request[$pageSizeArgument] ], $filterArgs);
            list($result, $pageCount) = call_user_func_array( [ $class, 'dbReadPaged' ], $dbReadPagedArgs );
        } else {
            $dbReadAllArgs = array_merge([ $sortColumn, $sortDir, $filter ], $filterArgs);
            $pageCount = 1;
            $result = \call_user_func_array( [ $class, 'dbReadAll' ], $dbReadAllArgs );
        }
        return [ 'result' => [
            'success' => true,
            'data' => [
                'pageCount' => $pageCount,
                'collection' => $result
            ]
        ]];
    }
}