<?php

namespace App\Enums;

use App\Enums\PermissionKey as P;

/**
 * The default school roles (SPEC section 5). The value is the Spatie role name.
 * Platform roles (Orange Tree super admin, support) belong to the console guard.
 */
enum RoleKey: string
{
    case Proprietor = 'proprietor';
    case Headteacher = 'headteacher';
    case Administrator = 'administrator';
    case Bursar = 'bursar';
    case ClassTeacher = 'class_teacher';
    case SubjectTeacher = 'subject_teacher';
    case HouseMaster = 'house_master';
    case GateStaff = 'gate_staff';
    case Parent = 'parent';

    public function label(): string
    {
        return match ($this) {
            self::Proprietor => 'Proprietor / Owner',
            self::Headteacher => 'Headteacher',
            self::Administrator => 'Administrator / Registrar',
            self::Bursar => 'Bursar / Accountant',
            self::ClassTeacher => 'Class teacher',
            self::SubjectTeacher => 'Subject teacher',
            self::HouseMaster => 'House master / mistress',
            self::GateStaff => 'Gate / pickup staff',
            self::Parent => 'Parent / Guardian',
        };
    }

    /**
     * Roles handling money or approvals must use two-factor authentication (SPEC section 5).
     */
    public function requiresTwoFactor(): bool
    {
        return in_array($this, [self::Proprietor, self::Headteacher, self::Administrator, self::Bursar], true);
    }

    /**
     * Permissions a new school starts with for this role. Schools may edit them later.
     *
     * @return list<P>
     */
    public function defaultPermissions(): array
    {
        return match ($this) {
            // Everything except the parent app.
            self::Proprietor => [
                P::SettingsManage, P::RolesManage, P::StaffManage,
                P::StudentsView, P::StudentsManage,
                P::AttendanceTake, P::AttendanceEditLocked,
                P::GradesEnter, P::GradesApprove,
                P::FeesManage, P::PaymentsRecord, P::FinanceView,
                P::MessagesParents, P::AnnouncementsSend,
                P::PickupRelease, P::PickupRestrict,
                P::BoardingManage, P::ExeatApprove,
                P::AuditView,
            ],
            self::Headteacher => [
                P::SettingsManage, P::RolesManage, P::StaffManage,
                P::StudentsView, P::StudentsManage,
                P::AttendanceTake, P::AttendanceEditLocked,
                P::GradesEnter, P::GradesApprove,
                P::FinanceView,
                P::MessagesParents, P::AnnouncementsSend,
                P::PickupRelease, P::PickupRestrict,
                P::BoardingManage, P::ExeatApprove,
                P::AuditView,
            ],
            self::Administrator => [
                P::SettingsManage, P::RolesManage, P::StaffManage,
                P::StudentsView, P::StudentsManage,
                P::AttendanceEditLocked,
                P::MessagesParents, P::AnnouncementsSend,
                P::PickupRestrict,
                P::AuditView,
            ],
            self::Bursar => [
                P::StudentsView,
                P::FeesManage, P::PaymentsRecord, P::FinanceView,
            ],
            self::ClassTeacher => [
                P::StudentsView,
                P::AttendanceTake,
                P::GradesEnter,
                P::MessagesParents, P::AnnouncementsSend,
                P::PickupRelease,
            ],
            self::SubjectTeacher => [
                P::StudentsView,
                P::GradesEnter,
                P::MessagesParents,
            ],
            self::HouseMaster => [
                P::StudentsView,
                P::MessagesParents,
                P::PickupRelease,
                P::BoardingManage, P::ExeatApprove,
            ],
            self::GateStaff => [
                P::PickupRelease,
            ],
            self::Parent => [
                P::PortalChildren,
            ],
        };
    }
}
