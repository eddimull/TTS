<?php

namespace App\Formatters;

class CalendarAccessFormatter
{
    public static function formatACLRule(string $email, string $role): \Google\Service\Calendar\AclRule
    {
        $rule = new \Google\Service\Calendar\AclRule();
        $scope = new \Google\Service\Calendar\AclRuleScope();
        $scope->setType('user'); // e.g., 'user', 'group', 'domain', 'default'
        $scope->setValue($email); // e.g., email address or domain
        $rule->setScope($scope);
        $rule->setRole($role); // e.g., 'reader', 'writer', 'owner'

        return $rule;
    }
}