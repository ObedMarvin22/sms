<?php

namespace App\Enums;

/**
 * Permission names (Spatie permissions are shared by all schools; roles are per school).
 */
enum PermissionKey: string
{
    case SettingsManage = 'settings.manage';
    case RolesManage = 'roles.manage';
    case StaffManage = 'staff.manage';
    case StudentsView = 'students.view';
    case StudentsManage = 'students.manage';
    case AttendanceTake = 'attendance.take';
    case AttendanceEditLocked = 'attendance.edit_locked';
    case GradesEnter = 'grades.enter';
    case GradesApprove = 'grades.approve';
    case FeesManage = 'fees.manage';
    case PaymentsRecord = 'payments.record';
    case FinanceView = 'finance.view';
    case MessagesParents = 'messages.parents';
    case AnnouncementsSend = 'announcements.send';
    case PickupRelease = 'pickup.release';
    case PickupRestrict = 'pickup.restrict';
    case BoardingManage = 'boarding.manage';
    case ExeatApprove = 'exeat.approve';
    case AuditView = 'audit.view';
    case PortalChildren = 'portal.children';

    /**
     * Money, approvals and admin powers. To give or remove a role carrying one of these you must
     * hold it yourself (StaffRules). Everyday teaching permissions are not sensitive.
     */
    public function isSensitive(): bool
    {
        return in_array($this, [
            self::SettingsManage, self::RolesManage, self::StaffManage,
            self::AttendanceEditLocked, self::GradesApprove,
            self::FeesManage, self::PaymentsRecord, self::FinanceView,
            self::PickupRestrict, self::AuditView,
        ], true);
    }
}
