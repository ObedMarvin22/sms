<?php

use App\Enums\PermissionKey;
use App\Enums\RoleKey;

// Role and permission names are stored in the database: renaming one breaks existing schools.

it('keeps the stored role names', function () {
    expect(array_column(RoleKey::cases(), 'value'))->toBe([
        'proprietor', 'headteacher', 'administrator', 'bursar', 'class_teacher',
        'subject_teacher', 'house_master', 'gate_staff', 'parent',
    ]);
});

it('keeps the stored permission names', function () {
    expect(array_column(PermissionKey::cases(), 'value'))->toBe([
        'settings.manage', 'roles.manage', 'staff.manage', 'students.view', 'students.manage',
        'attendance.take', 'attendance.edit_locked', 'grades.enter', 'grades.approve',
        'fees.manage', 'payments.record', 'finance.view', 'messages.parents',
        'announcements.send', 'pickup.release', 'pickup.restrict', 'boarding.manage',
        'exeat.approve', 'audit.view', 'portal.children',
    ]);
});

it('requires two-factor for roles handling money or approvals', function () {
    $twoFactor = array_values(array_filter(RoleKey::cases(), fn (RoleKey $role) => $role->requiresTwoFactor()));

    expect($twoFactor)->toBe([RoleKey::Proprietor, RoleKey::Headteacher, RoleKey::Administrator, RoleKey::Bursar]);
});

it('lists each default permission at most once per role', function () {
    foreach (RoleKey::cases() as $role) {
        $values = array_map(fn (PermissionKey $p) => $p->value, $role->defaultPermissions());

        expect($values)->toBe(array_values(array_unique($values)), "{$role->value} has duplicates");
    }
});

it('gives the proprietor every permission except the parent app', function () {
    $expected = array_values(array_filter(PermissionKey::cases(), fn (PermissionKey $p) => $p !== PermissionKey::PortalChildren));

    expect(RoleKey::Proprietor->defaultPermissions())->toEqualCanonicalizing($expected);
});

it('labels every role', function () {
    expect(RoleKey::Bursar->label())->toBe('Bursar / Accountant')
        ->and(RoleKey::GateStaff->label())->toBe('Gate / pickup staff');
});
