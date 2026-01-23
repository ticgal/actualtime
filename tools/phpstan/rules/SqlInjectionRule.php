<?php

declare(strict_types=1);

namespace CustomPHPStanRules;

use PhpParser\Node;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects SQL queries constructed manually with string concatenation.
 */
class SqlInjectionRule implements Rule
{
    public function getNodeType(): string
    {
        return Assign::class; // Analyzes variable assignments.
    }

    /**
     * @param Assign $node
     * @param Scope $scope
     * @return array
     */
    public function processNode(Node $node, Scope $scope): array
    {
        // Check if the assigned value is a concatenation
        if ($node->expr instanceof Concat) {
            $left = $node->expr->left;
            $right = $node->expr->right;

            // Skip if neither side is a string literal
            if (!($left instanceof String_) && !($right instanceof String_)) {
                return [];
            }

            $variableName = $node->var->name ?? null;
            $sqlKeywords = ['sql', 'query', 'statement', 'db'];
            if (
                is_string($variableName)
                && preg_match('/(' . implode('|', $sqlKeywords) . ')/i', $variableName) === 0
            ) {
                return []; // Skip if the variable name doesn't suggest SQL
            }

            // Check if the left or right side of the concatenation is a string containing SQL keywords
            if (
                ($left instanceof String_ && $this->containsSqlKeywords($left->value)) ||
                ($right instanceof String_ && $this->containsSqlKeywords($right->value))
            ) {
                return [
                    RuleErrorBuilder::message(
                        'Detected a SQL query constructed with string concatenation, which may lead to SQL injection vulnerabilities. Use prepared statements instead.',
                    )->build(),
                ];
            }
        }

        return [];
    }

    /**
     * Checks if a string contains common SQL keywords.
     *
     * @param string $value
     * @return bool
     */
    private function containsSqlKeywords(string $value): bool
    {
        $keywords = ['SELECT', 'INSERT', 'UPDATE', 'DELETE', 'WHERE', 'FROM', 'JOIN', 'DROP', 'ALTER'];
        foreach ($keywords as $keyword) {
            if (stripos($value, $keyword) !== false) {
                return true;
            }
        }
        return false;
    }
}
