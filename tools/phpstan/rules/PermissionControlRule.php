<?php

declare(strict_types=1);

namespace CustomPHPStanRules;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects if a class in Config.php or config.class.php has the $rightname attribute with the value 'config'.
 */
class PermissionControlRule implements Rule
{
    public function getNodeType(): string
    {
        return Class_::class; // Analyzes class declarations.
    }

    /**
     * @param Class_ $node
     * @param Scope $scope
     * @return array
     */
    public function processNode(Node $node, Scope $scope): array
    {
        // Get the file path being analyzed
        $filePath = $scope->getFile();

        // Apply the rule only to Config.php or config.class.php
        if (!preg_match('/(\/|\\\\)(Config\.php|config\.class\.php)$/i', $filePath)) {
            return []; // Skip files that don't match
        }

        // Check if the class has a property named $rightname
        foreach ($node->getProperties() as $property) {
            if ($property instanceof Property && $property->props[0]->name->toString() === 'rightname') {
                $default = $property->props[0]->default;

                // Check if the default value of $rightname is 'config'
                if ($default instanceof String_ && $default->value === 'config') {
                    return []; // No error if $rightname is correctly set to 'config'
                }

                return [
                    RuleErrorBuilder::message(
                        'The class in Config.php or config.class.php must have the $rightname attribute set to "config".',
                    )->build(),
                ];
            }
        }

        // If $rightname is not defined, report an error
        return [
            RuleErrorBuilder::message(
                'The class in Config.php or config.class.php must define the $rightname attribute with the value "config".',
            )->build(),
        ];
    }
}
