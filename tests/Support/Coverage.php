<?php

namespace LowListTests;

final class Coverage
{
    /**
     * Collect the loaded declarations without autoloading or invoking source code.
     *
     * @param string $sourceRoot
     * @return array
     */
    public static function declarations($sourceRoot)
    {
        $declarations = array();
        $remember = static function ($name, $reflection) use (&$declarations, $sourceRoot) {
            if (!self::belongsToSource($reflection->getFileName(), $sourceRoot)) {
                return;
            }
            $declarations[$name] = array(
                'file' => $reflection->getFileName(),
                'line_start' => $reflection->getStartLine(),
                'line_end' => $reflection->getEndLine(),
            );
        };
        foreach (get_declared_classes() as $class) {
            $reflection = new \ReflectionClass($class);
            if (!self::belongsToSource($reflection->getFileName(), $sourceRoot)) {
                continue;
            }
            foreach ($reflection->getMethods() as $method) {
                if ($method->getDeclaringClass()->getName() === $class) {
                    $remember($class . '->' . $method->getName(), $method);
                }
            }
        }
        foreach (get_defined_functions()['user'] as $function) {
            $remember($function, new \ReflectionFunction($function));
        }
        foreach ((array) spl_autoload_functions() as $autoload) {
            if ($autoload instanceof \Closure) {
                $reflection = new \ReflectionFunction($autoload);
                $name = '{closure:' . $reflection->getFileName() . ':' . $reflection->getStartLine() . '-' . $reflection->getEndLine() . '}';
                $remember($name, $reflection);
            }
        }

        return $declarations;
    }

    /**
     * Restrict measurements to the original loose source and original archive.
     *
     * @param string|false $file
     * @param string $sourceRoot
     * @return bool
     */
    public static function belongsToSource($file, $sourceRoot)
    {
        return is_string($file)
            && (strpos($file, $sourceRoot) === 0 || strpos($file, 'phar://' . $sourceRoot) === 0);
    }

    /**
     * Check raw graph ranges against the declaration actually loaded at runtime.
     *
     * @param string $file
     * @param string $name
     * @param array $branches
     * @param array $declarations
     * @return array
     */
    public static function attribution($file, $name, array $branches, array $declarations)
    {
        if (!isset($declarations[$name])) {
            return array('status' => 'unknown', 'reason' => 'no_matching_runtime_declaration', 'declaration' => null);
        }
        $declaration = $declarations[$name];
        if ($file !== $declaration['file']) {
            return array('status' => 'mismatched', 'reason' => 'different_runtime_source', 'declaration' => $declaration);
        }
        foreach ($branches as $branch) {
            if ($branch['line_start'] < $declaration['line_start'] || $branch['line_end'] > $declaration['line_end']) {
                return array('status' => 'mismatched', 'reason' => 'graph_outside_runtime_range', 'declaration' => $declaration);
            }
        }
        if (!$branches) {
            return array('status' => 'unknown', 'reason' => 'no_native_graph', 'declaration' => $declaration);
        }

        return array('status' => 'matched', 'reason' => null, 'declaration' => $declaration);
    }
}
