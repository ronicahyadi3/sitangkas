@extends('layouts.app')

@section('styling')
    <style>
        .modal-lg {
            max-width: 980px;
        }

        .dt-center {
            text-align: center;
        }

        .users-page {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .users-hero {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1.25rem;
            padding: 1.15rem 1.25rem;
            border-radius: 1rem;
            color: #0f172a;
            background: #fff;
            border: 0;
            box-shadow: 0 12px 28px rgba(15, 23, 42, 0.06);
        }

        .users-hero__eyebrow {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            padding: 0;
            color: #8392ab;
            font-size: .76rem;
            font-weight: 800;
            letter-spacing: .04em;
            text-transform: uppercase;
        }

        .users-hero__title {
            margin: .3rem 0 .25rem;
            font-size: 1.28rem;
            font-weight: 700;
            line-height: 1.25;
        }

        .users-hero__desc {
            max-width: 640px;
            margin: 0;
            color: #475569;
            font-size: .9rem;
        }

        .users-hero__chips {
            display: flex;
            flex-wrap: wrap;
            gap: .5rem;
            margin-top: .8rem;
        }

        .users-chip {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            padding: .34rem .65rem;
            border-radius: .65rem;
            background: #f8fafc;
            border: 1px solid rgba(148, 163, 184, 0.2);
            color: #334155;
            font-size: .78rem;
            font-weight: 600;
        }

        .users-chip i {
            color: #2563eb;
        }

        .users-hero__cta {
            flex-shrink: 0;
            display: flex;
            align-items: flex-start;
            min-height: 100%;
        }

        .users-stat-card {
            height: 100%;
            border: 0;
            border-radius: 1rem;
            background: #fff;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.055);
            overflow: hidden;
        }

        .users-stat-card .card-body {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: .9rem;
            padding: 1rem 1.05rem;
        }

        .users-stat-card__content {
            min-width: 0;
        }

        .users-stat-card__label {
            color: #64748b;
            font-size: .76rem;
            font-weight: 700;
            letter-spacing: .03em;
            text-transform: uppercase;
        }

        .users-stat-card__value {
            margin-top: .4rem;
            color: #0f172a;
            font-size: 1.45rem;
            font-weight: 700;
            line-height: 1.1;
        }

        .users-stat-card__helper {
            margin-top: .4rem;
            color: #64748b;
            font-size: .8rem;
            line-height: 1.35;
        }

        .users-stat-card__icon {
            flex: 0 0 auto;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            border-radius: .85rem;
            color: #fff;
            font-size: 1rem;
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.16);
        }

        .users-stat-card__icon--primary {
            background: linear-gradient(310deg, #2152ff, #21d4fd);
        }

        .users-stat-card__icon--success {
            background: linear-gradient(310deg, #17ad37, #98ec2d);
        }

        .users-stat-card__icon--info {
            background: linear-gradient(310deg, #1171ef, #11cdef);
        }

        .users-stat-card__icon--dark {
            background: linear-gradient(310deg, #344767, #111827);
        }

        .users-panel {
            border: 0;
            border-radius: 1rem;
            box-shadow: 0 16px 34px rgba(15, 23, 42, 0.06);
            overflow: visible;
        }

        .users-table-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1rem;
            padding: 1.25rem 1.25rem .75rem;
        }

        .users-table-toolbar h5 {
            margin-bottom: .35rem;
            font-weight: 700;
        }

        .users-table-toolbar p {
            margin-bottom: 0;
            color: #64748b;
            font-size: .92rem;
        }

        .users-inline-note {
            margin: 0 1.25rem 1rem;
            padding: .8rem 1rem;
            border-radius: .9rem;
            background: #f8fafc;
            color: #475569;
            font-size: .88rem;
            border: 1px dashed rgba(148, 163, 184, 0.32);
        }

        .users-filter-panel {
            margin: 0 1.25rem 1rem;
            padding: 1rem;
            border: 1px solid rgba(148, 163, 184, 0.24);
            border-radius: 1rem;
            background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
            box-shadow: 0 12px 30px rgba(15, 23, 42, 0.04);
        }

        .users-filter-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            margin-bottom: .95rem;
        }

        .users-filter-heading {
            display: flex;
            align-items: flex-start;
            gap: .75rem;
            min-width: 0;
        }

        .users-filter-icon {
            flex: 0 0 auto;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 38px;
            height: 38px;
            border-radius: .85rem;
            color: #2152ff;
            background: rgba(33, 82, 255, 0.08);
        }

        .users-filter-title {
            color: #0f172a;
            font-size: .95rem;
            font-weight: 800;
            line-height: 1.2;
        }

        .users-filter-summary {
            display: inline-flex;
            align-items: center;
            gap: .38rem;
            margin-top: .35rem;
            padding: .28rem .58rem;
            border: 1px solid rgba(148, 163, 184, 0.2);
            border-radius: 999px;
            color: #475569;
            background: #f1f5f9;
            font-size: .76rem;
            font-weight: 800;
        }

        .users-filter-body {
            display: grid;
            gap: .9rem;
        }

        .users-filter-group {
            padding: .2rem 0 0;
        }

        .users-filter-group + .users-filter-group {
            padding-top: .9rem;
            border-top: 1px solid rgba(148, 163, 184, 0.18);
        }

        .users-filter-group__header {
            display: flex;
            align-items: center;
            gap: .45rem;
            margin-bottom: .75rem;
            color: #334155;
            font-size: .78rem;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: .03em;
        }

        .users-filter-group__header i {
            color: #2152ff;
        }

        .users-filter-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: .75rem;
        }

        .users-filter-field {
            min-width: 0;
            padding: .72rem;
            border: 1px solid rgba(148, 163, 184, 0.16);
            border-radius: .85rem;
            background: #ffffff;
        }

        .users-filter-panel .form-label {
            margin-bottom: .35rem;
            color: #334155;
            font-size: .78rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .03em;
        }

        .users-filter-actions {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: .5rem;
        }

        .users-filter-actions .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .35rem;
            min-height: 2.35rem;
            border-radius: .75rem;
            font-weight: 800;
        }

        .users-filter-chips {
            display: flex;
            flex-wrap: wrap;
            gap: .5rem;
            margin-top: .85rem;
            padding-top: .85rem;
            border-top: 1px solid rgba(148, 163, 184, 0.18);
        }

        .users-active-filter-chip {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            max-width: 100%;
            padding: .38rem .55rem .38rem .7rem;
            border: 1px solid rgba(33, 82, 255, 0.14);
            border-radius: .75rem;
            color: #1e3a8a;
            background: rgba(59, 130, 246, 0.08);
            font-size: .78rem;
            font-weight: 800;
        }

        .users-active-filter-chip span {
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .users-active-filter-chip button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 22px;
            height: 22px;
            padding: 0;
            border: 0;
            border-radius: .5rem;
            color: #1d4ed8;
            background: rgba(255, 255, 255, 0.72);
        }

        .users-active-filter-chip button:hover {
            color: #fff;
            background: #2152ff;
        }

        .users-table-wrap {
            padding: 0 1.25rem 1.25rem;
        }

        .users-table {
            margin-bottom: 0 !important;
        }

        .users-table thead th {
            padding-top: .95rem;
            padding-bottom: .95rem;
            color: #475569;
            font-size: .84rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .03em;
            border-bottom-width: 1px;
        }

        .users-table tbody td {
            vertical-align: middle;
            padding-top: 1rem;
            padding-bottom: 1rem;
        }

        .users-table-cell__title {
            color: #0f172a;
            font-weight: 600;
            line-height: 1.35;
        }

        .users-table-cell__meta {
            margin-top: .2rem;
            color: #64748b;
            font-size: .83rem;
        }

        .users-count-pill,
        .users-muted-pill,
        .users-meta-pill {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            padding: .4rem .75rem;
            border-radius: 999px;
            font-size: .82rem;
            font-weight: 700;
        }

        .users-count-pill {
            color: #0f766e;
            background: rgba(20, 184, 166, 0.12);
        }

        .users-muted-pill {
            color: #475569;
            background: #e2e8f0;
        }

        .users-meta-pill {
            color: #334155;
            background: #f1f5f9;
            border: 1px solid rgba(148, 163, 184, 0.22);
        }

        .users-badge-stack {
            display: inline-flex;
            align-items: center;
            flex-wrap: wrap;
            gap: .45rem;
            max-width: 100%;
        }

        .users-badge-stack--center {
            justify-content: center;
        }

        .users-account-badge,
        .users-type-badge,
        .users-year-pill,
        .users-position-pill,
        .users-security-badge {
            display: inline-flex;
            align-items: center;
            gap: .38rem;
            max-width: 100%;
            padding: .38rem .62rem;
            border-radius: .72rem;
            font-size: .76rem;
            font-weight: 800;
            line-height: 1.1;
            white-space: nowrap;
        }

        .users-account-badge--active {
            color: #166534;
            background: rgba(34, 197, 94, 0.12);
            border: 1px solid rgba(34, 197, 94, 0.16);
        }

        .users-account-badge--pending {
            color: #92400e;
            background: rgba(245, 158, 11, 0.13);
            border: 1px solid rgba(245, 158, 11, 0.18);
        }

        .users-account-badge--inactive {
            color: #475569;
            background: #e2e8f0;
            border: 1px solid rgba(148, 163, 184, 0.18);
        }

        .users-account-badge--locked,
        .users-account-badge--suspended {
            color: #991b1b;
            background: rgba(239, 68, 68, 0.12);
            border: 1px solid rgba(239, 68, 68, 0.18);
        }

        .users-type-badge {
            color: #1e3a8a;
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid rgba(59, 130, 246, 0.14);
        }

        .users-type-badge--functional {
            color: #6d28d9;
            background: rgba(139, 92, 246, 0.1);
            border-color: rgba(139, 92, 246, 0.14);
        }

        .users-type-badge--service {
            color: #0f766e;
            background: rgba(20, 184, 166, 0.1);
            border-color: rgba(20, 184, 166, 0.15);
        }

        .users-type-badge--emergency {
            color: #9a3412;
            background: rgba(249, 115, 22, 0.12);
            border-color: rgba(249, 115, 22, 0.18);
        }

        .users-year-pill {
            color: #334155;
            background: #f8fafc;
            border: 1px solid rgba(148, 163, 184, 0.2);
        }

        .users-position-pill--active {
            color: #0f766e;
            background: rgba(20, 184, 166, 0.12);
            border: 1px solid rgba(20, 184, 166, 0.16);
        }

        .users-position-pill--used {
            color: #0f766e;
            background: rgba(20, 184, 166, 0.11);
            border: 1px solid rgba(20, 184, 166, 0.15);
        }

        .users-position-pill--missing {
            color: #92400e;
            background: rgba(245, 158, 11, 0.13);
            border: 1px solid rgba(245, 158, 11, 0.18);
        }

        .users-position-pill--inactive {
            color: #991b1b;
            background: rgba(239, 68, 68, 0.12);
            border: 1px solid rgba(239, 68, 68, 0.18);
        }

        .users-position-pill--history {
            color: #475569;
            background: #f1f5f9;
            border: 1px solid rgba(148, 163, 184, 0.2);
        }

        .users-position-pill--unused {
            color: #1e3a8a;
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid rgba(59, 130, 246, 0.14);
        }

        .users-security-badge--ok {
            color: #166534;
            background: rgba(34, 197, 94, 0.11);
            border: 1px solid rgba(34, 197, 94, 0.15);
        }

        .users-security-badge--locked {
            color: #991b1b;
            background: rgba(239, 68, 68, 0.12);
            border: 1px solid rgba(239, 68, 68, 0.18);
        }

        .users-security-badge--password-ok {
            color: #1e3a8a;
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid rgba(59, 130, 246, 0.14);
        }

        .users-security-badge--password-required {
            color: #92400e;
            background: rgba(245, 158, 11, 0.13);
            border: 1px solid rgba(245, 158, 11, 0.18);
        }

        .users-security-badge--mfa-active {
            color: #0f766e;
            background: rgba(20, 184, 166, 0.11);
            border: 1px solid rgba(20, 184, 166, 0.15);
        }

        .users-security-badge--mfa-pending {
            color: #6d28d9;
            background: rgba(139, 92, 246, 0.1);
            border: 1px solid rgba(139, 92, 246, 0.14);
        }

        .users-security-badge--mfa-missing {
            color: #475569;
            background: #e2e8f0;
            border: 1px solid rgba(148, 163, 184, 0.18);
        }

        .users-action-btn {
            border-radius: .75rem;
            min-width: 2.45rem;
        }

        .users-action-dropdown {
            display: inline-flex;
            justify-content: center;
        }

        .users-action-menu-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2.35rem;
            height: 2.35rem;
            padding: 0;
            border-radius: .8rem;
            color: #344767;
            background: #fff;
            border-color: rgba(148, 163, 184, 0.32);
        }

        .users-action-menu {
            min-width: 13.5rem;
            padding: .45rem;
            border: 1px solid rgba(148, 163, 184, 0.18);
            border-radius: .85rem;
            box-shadow: 0 14px 32px rgba(15, 23, 42, 0.14);
        }

        .users-action-menu .dropdown-item {
            display: flex;
            align-items: center;
            gap: .7rem;
            min-height: 2.3rem;
            border-radius: .65rem;
            color: #344767;
            font-size: .86rem;
            font-weight: 700;
        }

        .users-action-menu .dropdown-item i {
            width: 1rem;
            text-align: center;
            color: #64748b;
        }

        .users-action-menu .dropdown-item:hover {
            color: #1d4ed8;
            background: rgba(33, 82, 255, 0.08);
        }

        .users-action-menu .dropdown-item.text-danger,
        .users-action-menu .dropdown-item.text-danger i {
            color: #dc2626 !important;
        }

        .users-modal-header {
            align-items: flex-start;
            padding-bottom: 1rem;
        }

        .users-modal-title {
            margin-bottom: .3rem;
            font-weight: 700;
        }

        .users-modal-subtitle {
            margin-bottom: 0;
            color: #64748b;
            font-size: .9rem;
        }

        .users-section-card {
            border: 1px solid rgba(148, 163, 184, 0.2);
            border-radius: 1rem;
            background: #fff;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.8);
        }

        .users-section-card__header {
            padding: 1rem 1rem 0;
        }

        .users-section-card__heading {
            display: flex;
            align-items: center;
            gap: .65rem;
        }

        .users-section-card__icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 34px;
            height: 34px;
            border-radius: .75rem;
            color: #2152ff;
            background: rgba(33, 82, 255, 0.08);
        }

        .users-section-card__title {
            margin-bottom: .25rem;
            font-size: 1rem;
            font-weight: 700;
        }

        .users-section-card__desc {
            margin-bottom: 0;
            color: #64748b;
            font-size: .88rem;
        }

        .users-section-card__body {
            padding: 1rem;
        }

        .users-helper {
            padding: .85rem 1rem;
            border-radius: .95rem;
            background: rgba(37, 99, 235, 0.06);
            color: #334155;
            font-size: .9rem;
            border: 1px solid rgba(37, 99, 235, 0.12);
        }

        .users-user-modal-grid {
            display: grid;
            grid-template-columns: 240px minmax(0, 1fr);
            gap: 1rem;
            align-items: start;
        }

        .users-user-modal-aside {
            padding: 1rem;
            border: 1px solid rgba(148, 163, 184, 0.2);
            border-radius: 1rem;
            background: linear-gradient(180deg, #f8fafc 0%, #ffffff 100%);
        }

        .users-user-modal-avatar {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 46px;
            height: 46px;
            border-radius: 1rem;
            color: #fff;
            background: linear-gradient(310deg, #2152ff, #21d4fd);
            box-shadow: 0 10px 22px rgba(33, 82, 255, 0.22);
        }

        .users-user-modal-aside-title {
            margin-top: .85rem;
            color: #0f172a;
            font-size: 1rem;
            font-weight: 800;
        }

        .users-user-modal-aside-desc {
            margin: .35rem 0 0;
            color: #64748b;
            font-size: .82rem;
            line-height: 1.45;
        }

        .users-user-modal-steps {
            display: grid;
            gap: .55rem;
            margin-top: 1rem;
        }

        .users-user-modal-step {
            display: flex;
            align-items: center;
            gap: .55rem;
            padding: .55rem .65rem;
            border: 1px solid rgba(148, 163, 184, 0.18);
            border-radius: .8rem;
            color: #334155;
            background: #fff;
            font-size: .8rem;
            font-weight: 800;
        }

        .users-user-modal-step i {
            width: 1rem;
            color: #2152ff;
            text-align: center;
        }

        .users-user-form-stack {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .users-password-note {
            height: 100%;
            display: flex;
            align-items: flex-start;
            gap: .65rem;
        }

        .users-password-note i {
            margin-top: .12rem;
            color: #2563eb;
        }

        .users-position-form-stack {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .users-position-form-stack--compact {
            gap: .75rem;
        }

        .users-position-form-card .users-section-card__header {
            padding-top: .85rem;
        }

        .users-position-form-card .users-section-card__body {
            padding-top: .85rem;
        }

        .users-position-form-group {
            padding: .95rem;
            border: 1px solid rgba(148, 163, 184, 0.18);
            border-radius: .95rem;
            background: #fff;
        }

        .users-position-form-stack--compact .users-position-form-group {
            padding: .72rem;
            border-radius: .82rem;
        }

        .users-position-form-group__header {
            display: flex;
            align-items: flex-start;
            gap: .65rem;
            margin-bottom: .85rem;
        }

        .users-position-form-stack--compact .users-position-form-group__header {
            align-items: center;
            gap: .55rem;
            margin-bottom: .62rem;
        }

        .users-position-form-group__icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: .72rem;
            color: #2152ff;
            background: rgba(33, 82, 255, 0.08);
        }

        .users-position-form-stack--compact .users-position-form-group__icon {
            width: 28px;
            height: 28px;
            border-radius: .62rem;
            font-size: .82rem;
        }

        .users-position-form-group__title {
            margin: 0;
            color: #0f172a;
            font-size: .9rem;
            font-weight: 800;
            line-height: 1.2;
        }

        .users-position-form-group__desc {
            margin: .18rem 0 0;
            color: #64748b;
            font-size: .78rem;
            line-height: 1.35;
        }

        .users-position-form-stack--compact .users-position-form-group__desc {
            font-size: .74rem;
        }

        .users-position-form-stack--compact .form-label {
            margin-bottom: .28rem;
            color: #334155;
            font-size: .78rem;
            font-weight: 800;
        }

        .users-position-form-stack--compact .form-control,
        .users-position-form-stack--compact .form-select,
        .users-position-form-stack--compact .select2-container--bootstrap-5 .select2-selection {
            min-height: 2.35rem;
            border-radius: .68rem;
            font-size: .86rem;
        }

        .users-position-active-box {
            display: flex;
            align-items: flex-start;
            gap: .7rem;
            padding: .8rem .9rem;
            border: 1px solid rgba(20, 184, 166, 0.16);
            border-radius: .9rem;
            background: rgba(20, 184, 166, 0.08);
        }

        .users-position-active-box--compact {
            height: 100%;
            min-height: 2.35rem;
            align-items: center;
            padding: .62rem .75rem;
            border-radius: .72rem;
            font-size: .84rem;
            font-weight: 700;
        }

        .users-position-compact-helper {
            margin-top: .35rem;
            color: #64748b;
            font-size: .76rem;
            line-height: 1.35;
        }

        .users-position-guidance {
            display: flex;
            align-items: flex-start;
            gap: .8rem;
            margin-bottom: 1rem;
            padding: .95rem 1rem;
            border: 1px solid rgba(37, 99, 235, 0.12);
            border-radius: .95rem;
            color: #334155;
            background: rgba(37, 99, 235, 0.05);
            font-size: .86rem;
            line-height: 1.5;
        }

        .users-position-guidance i {
            margin-top: .15rem;
            color: #2563eb;
        }

        .users-position-access-panel {
            margin-bottom: 1rem;
            padding: .85rem;
            border: 1px solid rgba(148, 163, 184, 0.18);
            border-radius: .95rem;
            background: #fff;
        }

        .users-year-stepper {
            display: grid;
            grid-template-columns: 2.65rem minmax(5.5rem, 1fr) 2.65rem;
            align-items: stretch;
            overflow: hidden;
            min-height: 2.65rem;
            border: 1px solid rgba(148, 163, 184, 0.28);
            border-radius: .85rem;
            background: #f8fafc;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.8);
        }

        .users-year-stepper__btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 0;
            background: #fff;
            color: #334155;
            transition: color .15s ease, background .15s ease, opacity .15s ease;
        }

        .users-year-stepper__btn:hover:not(:disabled) {
            color: #2152ff;
            background: rgba(33, 82, 255, 0.08);
        }

        .users-year-stepper__btn:disabled {
            cursor: not-allowed;
            opacity: .45;
        }

        .users-year-stepper__input {
            min-height: 2.65rem;
            border: 0 !important;
            border-right: 1px solid rgba(148, 163, 184, 0.24) !important;
            border-left: 1px solid rgba(148, 163, 184, 0.24) !important;
            border-radius: 0 !important;
            background: #fff !important;
            color: #0f172a;
            font-size: 1rem;
            font-weight: 800;
            text-align: center;
            box-shadow: none !important;
        }

        .users-year-stepper__input::-webkit-outer-spin-button,
        .users-year-stepper__input::-webkit-inner-spin-button {
            margin: 0;
            -webkit-appearance: none;
        }

        .users-year-stepper__caption {
            display: flex;
            align-items: center;
            gap: .42rem;
            margin-top: .5rem;
            color: #64748b;
            font-size: .78rem;
            font-weight: 700;
            line-height: 1.35;
        }

        .users-year-stepper__caption i {
            color: #2563eb;
        }

        .users-position-layout {
            display: grid;
            gap: 1rem;
        }

        .users-position-form-actions {
            display: flex;
            justify-content: flex-end;
            flex-wrap: wrap;
            gap: .55rem;
            padding-top: .05rem;
        }

        .users-position-form-actions .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .35rem;
            min-height: 2.55rem;
            padding-right: 1rem;
            padding-left: 1rem;
            border-radius: .8rem;
            font-weight: 800;
        }

        .users-danger-note {
            padding: .95rem 1rem;
            border-radius: .95rem;
            background: rgba(239, 68, 68, 0.08);
            color: #991b1b;
            border: 1px solid rgba(239, 68, 68, 0.12);
        }

        .users-security-reason-alert,
        .users-admin-reason-alert {
            display: flex;
            align-items: flex-start;
            gap: .8rem;
            padding: .95rem 1rem;
            border: 1px solid rgba(245, 158, 11, 0.18);
            border-radius: .95rem;
            color: #78350f;
            background: rgba(245, 158, 11, 0.1);
        }

        .users-security-reason-alert__icon,
        .users-admin-reason-alert__icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2.15rem;
            height: 2.15rem;
            flex: 0 0 2.15rem;
            border-radius: .75rem;
            color: #fff;
            background: linear-gradient(310deg, #f59e0b, #f97316);
            box-shadow: 0 .65rem 1.1rem rgba(245, 158, 11, 0.2);
        }

        .users-security-reason-alert__title,
        .users-admin-reason-alert__title {
            margin-bottom: .15rem;
            font-size: .86rem;
            font-weight: 800;
            line-height: 1.25;
        }

        .users-security-reason-alert__desc,
        .users-admin-reason-alert__desc {
            font-size: .84rem;
            font-weight: 600;
            line-height: 1.45;
        }

        .users-security-reason-fields,
        .users-admin-reason-fields {
            display: grid;
            gap: .9rem;
        }

        .users-security-reason-field,
        .users-admin-reason-field {
            padding: .95rem;
            border: 1px solid rgba(148, 163, 184, 0.18);
            border-radius: .95rem;
            background: #fff;
        }

        .users-security-reason-field .form-label,
        .users-admin-reason-field .form-label {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            margin-bottom: .5rem;
        }

        .users-security-reason-field textarea,
        .users-admin-reason-field textarea {
            min-height: 8.25rem;
            resize: vertical;
        }

        .users-security-reason-audit,
        .users-admin-reason-audit {
            display: flex;
            align-items: flex-start;
            gap: .65rem;
            padding: .8rem .9rem;
            border: 1px solid rgba(37, 99, 235, 0.12);
            border-radius: .85rem;
            color: #1e3a8a;
            background: rgba(37, 99, 235, 0.06);
            font-size: .82rem;
            font-weight: 700;
            line-height: 1.45;
        }

        .users-security-reason-audit i,
        .users-admin-reason-audit i {
            margin-top: .12rem;
        }

        .users-historical-heading {
            display: flex;
            align-items: flex-start;
            gap: .85rem;
            min-width: 0;
        }

        .users-historical-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2.65rem;
            height: 2.65rem;
            flex: 0 0 2.65rem;
            border-radius: .85rem;
            color: #fff;
            background: linear-gradient(310deg, #2152ff, #21d4fd);
            box-shadow: 0 .75rem 1.35rem rgba(33, 82, 255, 0.22);
        }

        .users-historical-icon--revoke {
            background: linear-gradient(310deg, #f59e0b, #f97316);
            box-shadow: 0 .75rem 1.35rem rgba(245, 158, 11, 0.2);
        }

        .users-historical-context {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: .75rem;
            margin-bottom: .9rem;
        }

        .users-historical-context__item {
            min-width: 0;
            padding: .85rem .9rem;
            border: 1px solid rgba(148, 163, 184, 0.18);
            border-radius: .9rem;
            background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
        }

        .users-historical-context__label {
            display: flex;
            align-items: center;
            gap: .42rem;
            margin-bottom: .35rem;
            color: #64748b;
            font-size: .74rem;
            font-weight: 900;
            text-transform: uppercase;
        }

        .users-historical-context__label i {
            color: #2563eb;
        }

        .users-historical-context__value {
            color: #0f172a;
            font-size: .9rem;
            font-weight: 800;
            line-height: 1.28;
        }

        .users-historical-card {
            padding: .95rem;
            border: 1px solid rgba(148, 163, 184, 0.18);
            border-radius: .95rem;
            background: #fff;
        }

        .users-historical-card + .users-historical-card {
            margin-top: .85rem;
        }

        .users-historical-card__header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: .75rem;
            margin-bottom: .75rem;
        }

        .users-historical-card__title {
            display: flex;
            align-items: center;
            gap: .45rem;
            margin: 0;
            color: #0f172a;
            font-size: .9rem;
            font-weight: 900;
        }

        .users-historical-card__title i {
            color: #2563eb;
        }

        .users-historical-card__desc {
            margin: .22rem 0 0;
            color: #64748b;
            font-size: .8rem;
            line-height: 1.45;
        }

        .users-historical-required {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            padding: .3rem .55rem;
            border-radius: 999px;
            color: #92400e;
            background: rgba(245, 158, 11, 0.12);
            border: 1px solid rgba(245, 158, 11, 0.18);
            font-size: .72rem;
            font-weight: 800;
            white-space: nowrap;
        }

        .users-historical-card textarea {
            resize: vertical;
        }

        .users-historical-audit-note {
            display: flex;
            align-items: flex-start;
            gap: .6rem;
            margin-top: .65rem;
            padding: .72rem .82rem;
            border: 1px solid rgba(37, 99, 235, 0.12);
            border-radius: .82rem;
            color: #1e3a8a;
            background: rgba(37, 99, 235, 0.06);
            font-size: .78rem;
            font-weight: 700;
            line-height: 1.45;
        }

        .users-historical-audit-note i {
            margin-top: .1rem;
            color: #2563eb;
        }

        .users-historical-metadata-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: .75rem;
        }

        .users-historical-metadata-grid .form-label {
            margin-bottom: .35rem;
            color: #334155;
            font-size: .78rem;
            font-weight: 800;
        }

        .users-historical-field--wide {
            grid-column: 1 / -1;
        }

        .users-temporary-password-box {
            display: flex;
            align-items: stretch;
            gap: .6rem;
            padding: .75rem;
            border: 1px solid rgba(37, 99, 235, 0.14);
            border-radius: .95rem;
            background: rgba(37, 99, 235, 0.06);
        }

        .users-temporary-password-card {
            padding: .9rem;
            border: 1px solid rgba(148, 163, 184, 0.18);
            border-radius: 1rem;
            background: #fff;
        }

        .users-temporary-password-card__header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .75rem;
            margin-bottom: .65rem;
        }

        .users-temporary-password-card__title {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            color: #334155;
            font-size: .78rem;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: .03em;
        }

        .users-temporary-password-card__title i {
            color: #2563eb;
        }

        .users-temporary-password-status {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            padding: .28rem .55rem;
            border-radius: 999px;
            color: #92400e;
            background: rgba(245, 158, 11, 0.12);
            border: 1px solid rgba(245, 158, 11, 0.18);
            font-size: .73rem;
            font-weight: 800;
            white-space: nowrap;
        }

        .users-temporary-password-status--copied {
            color: #166534;
            background: rgba(34, 197, 94, 0.12);
            border-color: rgba(34, 197, 94, 0.18);
        }

        .users-temporary-password-value {
            display: flex;
            align-items: center;
            flex: 1 1 auto;
            min-width: 0;
            padding: .72rem .85rem;
            border: 1px dashed rgba(37, 99, 235, 0.28);
            border-radius: .75rem;
            background: #fff;
            color: #0f172a;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
            font-size: .96rem;
            font-weight: 800;
            line-height: 1.35;
            word-break: break-all;
        }

        .users-temporary-password-copy {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .35rem;
            min-width: 6rem;
            border-radius: .75rem;
            font-weight: 800;
        }

        .users-temporary-password-note {
            display: flex;
            align-items: flex-start;
            gap: .55rem;
            color: #475569;
            font-size: .82rem;
            font-weight: 700;
            line-height: 1.45;
        }

        .users-temporary-password-note i {
            margin-top: .12rem;
            color: #2563eb;
        }

        .users-temporary-password-checklist {
            display: grid;
            gap: .55rem;
            margin-top: .85rem;
        }

        .users-temporary-password-checkitem {
            display: flex;
            align-items: flex-start;
            gap: .55rem;
            color: #475569;
            font-size: .8rem;
            font-weight: 700;
            line-height: 1.45;
        }

        .users-temporary-password-checkitem i {
            margin-top: .12rem;
            color: #0f766e;
        }

        .users-empty-state {
            padding: 1.5rem 1rem;
            text-align: center;
            color: #64748b;
        }

        .users-empty-state i {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2.75rem;
            height: 2.75rem;
            margin-bottom: .8rem;
            border-radius: 999px;
            background: #eff6ff;
            color: #2563eb;
        }

        .users-position-summary {
            display: flex;
            flex-wrap: wrap;
            gap: .5rem;
            margin-top: .75rem;
        }

        .users-position-modal-heading {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
            width: 100%;
        }

        .users-position-modal-identity {
            display: flex;
            align-items: flex-start;
            gap: .85rem;
            min-width: 0;
        }

        .users-position-modal-avatar {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2.8rem;
            height: 2.8rem;
            flex: 0 0 2.8rem;
            border-radius: .8rem;
            color: #fff;
            background: linear-gradient(310deg, #2152ff, #21d4fd);
            box-shadow: 0 .7rem 1.25rem rgba(33, 82, 255, 0.22);
        }

        .users-position-modal-copy {
            min-width: 0;
        }

        .users-position-modal-meta {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            flex-wrap: wrap;
            gap: .5rem;
            max-width: 46%;
        }

        .users-position-modal-chip {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            max-width: 100%;
            padding: .44rem .68rem;
            border: 1px solid rgba(148, 163, 184, 0.2);
            border-radius: .7rem;
            color: #334155;
            background: #f8fafc;
            font-size: .78rem;
            font-weight: 800;
            line-height: 1.15;
        }

        .users-position-modal-chip span {
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .users-active-position-panel {
            margin-bottom: 1rem;
            padding: 1rem;
            border: 1px solid rgba(20, 184, 166, 0.16);
            border-radius: .85rem;
            background:
                linear-gradient(135deg, rgba(20, 184, 166, 0.1), rgba(33, 82, 255, 0.06)),
                #fff;
            box-shadow: 0 .8rem 1.6rem rgba(15, 23, 42, 0.06);
        }

        .users-active-position-panel--empty,
        .users-active-position-panel--loading {
            border-color: rgba(148, 163, 184, 0.2);
            background: #f8fafc;
        }

        .users-active-position-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
        }

        .users-active-position-title-wrap {
            display: flex;
            align-items: flex-start;
            gap: .75rem;
            min-width: 0;
        }

        .users-active-position-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2.5rem;
            height: 2.5rem;
            flex: 0 0 2.5rem;
            border-radius: .75rem;
            color: #0f766e;
            background: rgba(20, 184, 166, 0.14);
        }

        .users-active-position-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            margin-bottom: .3rem;
            color: #0f766e;
            font-size: .74rem;
            font-weight: 800;
            line-height: 1.2;
            text-transform: uppercase;
            letter-spacing: 0;
        }

        .users-active-position-title {
            margin: 0;
            color: #0f172a;
            font-size: 1.05rem;
            font-weight: 800;
            line-height: 1.25;
        }

        .users-active-position-subtitle {
            margin: .25rem 0 0;
            color: #64748b;
            font-size: .86rem;
            line-height: 1.45;
        }

        .users-active-position-badges {
            display: inline-flex;
            align-items: center;
            justify-content: flex-end;
            flex-wrap: wrap;
            gap: .45rem;
        }

        .users-active-position-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: .75rem;
            margin-top: 1rem;
        }

        .users-active-position-field {
            min-width: 0;
            padding: .78rem .85rem;
            border: 1px solid rgba(148, 163, 184, 0.18);
            border-radius: .75rem;
            background: rgba(255, 255, 255, 0.76);
        }

        .users-active-position-label {
            display: flex;
            align-items: center;
            gap: .4rem;
            margin-bottom: .35rem;
            color: #64748b;
            font-size: .72rem;
            font-weight: 800;
            line-height: 1.2;
            text-transform: uppercase;
            letter-spacing: 0;
        }

        .users-active-position-value {
            color: #0f172a;
            font-size: .9rem;
            font-weight: 800;
            line-height: 1.35;
            overflow-wrap: anywhere;
        }

        .users-active-position-helper {
            margin-top: .18rem;
            color: #64748b;
            font-size: .78rem;
            line-height: 1.35;
        }

        .users-active-position-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            flex-wrap: wrap;
            gap: .5rem;
            margin-top: 1rem;
            padding-top: .9rem;
            border-top: 1px solid rgba(148, 163, 184, 0.16);
        }

        .users-active-position-actions .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .35rem;
            border-radius: .72rem;
            font-weight: 800;
        }

        .users-position-list {
            min-width: 0;
        }

        .users-position-table-wrap {
            overflow-x: auto;
            overflow-y: visible;
            border: 1px solid rgba(148, 163, 184, 0.18);
            border-radius: .85rem;
            background: #fff;
        }

        .users-position-table {
            min-width: 920px;
            margin-bottom: 0 !important;
        }

        .users-position-table thead th {
            padding: .72rem .85rem;
            color: #64748b;
            background: #f8fafc;
            border-bottom: 1px solid rgba(148, 163, 184, 0.18);
            font-size: .72rem;
            font-weight: 900;
            line-height: 1.2;
            text-transform: uppercase;
            letter-spacing: 0;
            white-space: nowrap;
        }

        .users-position-table tbody td {
            padding: .78rem .85rem;
            border-bottom: 1px solid rgba(148, 163, 184, 0.12);
            vertical-align: middle;
        }

        .users-position-table tbody tr:last-child td {
            border-bottom: 0;
        }

        .users-position-table tbody tr.users-position-row--active td {
            background: rgba(20, 184, 166, 0.055);
        }

        .users-position-table-cell {
            min-width: 0;
        }

        .users-position-table-title {
            color: #0f172a;
            font-size: .9rem;
            font-weight: 800;
            line-height: 1.3;
            overflow-wrap: anywhere;
        }

        .users-position-table-meta {
            margin-top: .16rem;
            color: #64748b;
            font-size: .78rem;
            line-height: 1.35;
            overflow-wrap: anywhere;
        }

        .users-position-table-badges {
            display: inline-flex;
            align-items: center;
            flex-wrap: wrap;
            gap: .35rem;
        }

        .users-position-table-actions {
            display: flex;
            justify-content: flex-end;
        }

        .users-position-table-action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2.2rem;
            height: 2.2rem;
            border-radius: .7rem;
        }

        .users-position-table-menu {
            min-width: 13.5rem;
            padding: .45rem;
            border: 1px solid rgba(148, 163, 184, 0.18);
            border-radius: .85rem;
            box-shadow: 0 1rem 2rem rgba(15, 23, 42, 0.12);
        }

        .users-position-table-menu .dropdown-item {
            display: flex;
            align-items: center;
            gap: .55rem;
            border-radius: .6rem;
            color: #334155;
            font-size: .84rem;
            font-weight: 700;
        }

        .users-position-table-menu .dropdown-item i {
            width: 1rem;
            text-align: center;
            color: #64748b;
        }

        .users-position-table-menu .dropdown-item.text-danger,
        .users-position-table-menu .dropdown-item.text-danger i {
            color: #dc2626 !important;
        }

        .users-position-card {
            border: 1px solid rgba(148, 163, 184, 0.2);
            border-radius: 1rem;
            background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
            padding: 1rem;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.05);
        }

        .users-position-card__head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1rem;
            margin-bottom: .9rem;
        }

        .users-position-card__heading {
            min-width: 0;
        }

        .users-position-card__eyebrow {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            margin-bottom: .35rem;
            color: #64748b;
            font-size: .75rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .03em;
        }

        .users-position-card__title {
            margin: 0;
            color: #0f172a;
            font-size: 1rem;
            font-weight: 800;
            line-height: 1.25;
        }

        .users-position-card__subtitle {
            margin-top: .25rem;
            color: #64748b;
            font-size: .88rem;
        }

        .users-position-card__badges {
            display: inline-flex;
            align-items: center;
            justify-content: flex-end;
            flex-wrap: wrap;
            gap: .45rem;
        }

        .users-position-card__meta {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: .75rem;
            margin-bottom: 1rem;
        }

        .users-position-field--wide {
            grid-column: 1 / -1;
        }

        .users-position-field.users-position-field--section {
            padding: .2rem 0 .05rem;
            border: 0;
            border-radius: 0;
            background: transparent;
        }

        .users-position-field.users-position-field--section .users-position-field__label {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            margin-bottom: 0;
            color: #334155;
            font-size: .78rem;
            font-weight: 900;
        }

        .users-position-field.users-position-field--section .users-position-field__label i {
            color: #2152ff;
        }

        .users-position-field {
            padding: .7rem .8rem;
            border-radius: .85rem;
            background: rgba(255, 255, 255, 0.84);
            border: 1px solid rgba(148, 163, 184, 0.16);
        }

        .users-position-field__label {
            display: block;
            margin-bottom: .2rem;
            color: #64748b;
            font-size: .75rem;
            font-weight: 700;
            letter-spacing: .03em;
            text-transform: uppercase;
        }

        .users-position-field__value {
            color: #0f172a;
            font-size: .9rem;
            font-weight: 600;
            line-height: 1.4;
        }

        .users-position-field__helper {
            margin-top: .35rem;
            color: #64748b;
            font-size: .8rem;
            line-height: 1.45;
        }

        .users-security-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: .75rem;
        }

        .users-security-signal-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: .75rem;
        }

        .users-security-signal {
            display: flex;
            align-items: center;
            gap: .75rem;
            min-width: 0;
            padding: .85rem;
            border: 1px solid rgba(148, 163, 184, 0.18);
            border-radius: .95rem;
            background: #fff;
        }

        .users-security-signal__icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2.25rem;
            height: 2.25rem;
            flex: 0 0 2.25rem;
            border-radius: .75rem;
            color: #475569;
            background: #f1f5f9;
        }

        .users-security-signal__text {
            min-width: 0;
        }

        .users-security-signal__label {
            color: #64748b;
            font-size: .74rem;
            font-weight: 800;
            line-height: 1.2;
            text-transform: uppercase;
            letter-spacing: .03em;
        }

        .users-security-signal__value {
            margin-top: .15rem;
            color: #0f172a;
            font-size: .9rem;
            font-weight: 800;
            line-height: 1.25;
        }

        .users-security-signal--success {
            border-color: rgba(34, 197, 94, 0.18);
            background: rgba(34, 197, 94, 0.06);
        }

        .users-security-signal--success .users-security-signal__icon {
            color: #166534;
            background: rgba(34, 197, 94, 0.14);
        }

        .users-security-signal--warning {
            border-color: rgba(245, 158, 11, 0.2);
            background: rgba(245, 158, 11, 0.07);
        }

        .users-security-signal--warning .users-security-signal__icon {
            color: #92400e;
            background: rgba(245, 158, 11, 0.15);
        }

        .users-security-signal--danger {
            border-color: rgba(239, 68, 68, 0.2);
            background: rgba(239, 68, 68, 0.07);
        }

        .users-security-signal--danger .users-security-signal__icon {
            color: #991b1b;
            background: rgba(239, 68, 68, 0.14);
        }

        .users-security-signal--muted {
            border-color: rgba(148, 163, 184, 0.2);
            background: #f8fafc;
        }

        .users-security-overview {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 1rem;
            border: 1px solid rgba(94, 114, 228, 0.14);
            border-radius: 1rem;
            background:
                linear-gradient(135deg, rgba(94, 114, 228, 0.1), rgba(17, 205, 239, 0.08)),
                #fff;
        }

        .users-security-overview__content {
            display: flex;
            align-items: center;
            gap: .85rem;
            min-width: 0;
        }

        .users-security-overview__icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2.75rem;
            height: 2.75rem;
            flex: 0 0 2.75rem;
            border-radius: .85rem;
            color: #fff;
            background: linear-gradient(310deg, #5e72e4, #11cdef);
            box-shadow: 0 .75rem 1.4rem rgba(94, 114, 228, 0.18);
        }

        .users-security-overview__title {
            color: #0f172a;
            font-size: .98rem;
            font-weight: 800;
            line-height: 1.25;
        }

        .users-security-overview__desc {
            margin: .2rem 0 0;
            color: #64748b;
            font-size: .82rem;
            line-height: 1.45;
        }

        .users-security-overview__meta {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            padding: .55rem .75rem;
            border-radius: 999px;
            color: #1e3a8a;
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid rgba(59, 130, 246, 0.14);
            font-size: .78rem;
            font-weight: 700;
            white-space: nowrap;
        }

        .users-security-actions {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: .75rem;
        }

        .users-security-actions--single {
            grid-template-columns: minmax(0, 1fr);
        }

        .users-security-action-groups {
            display: grid;
            gap: .85rem;
        }

        .users-security-action-group {
            padding-top: .85rem;
            border-top: 1px solid rgba(148, 163, 184, 0.18);
        }

        .users-security-action-group:first-child {
            padding-top: 0;
            border-top: 0;
        }

        .users-security-action-group__header {
            display: flex;
            align-items: center;
            gap: .45rem;
            margin-bottom: .65rem;
            color: #334155;
            font-size: .78rem;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: .03em;
        }

        .users-security-action-group__header i {
            color: #2152ff;
        }

        .users-security-action {
            display: flex;
            align-items: center;
            gap: .75rem;
            min-height: 3.2rem;
            padding: .8rem .9rem;
            border-radius: .85rem;
            justify-content: flex-start;
            text-align: left;
            white-space: normal;
        }

        .users-security-action:disabled {
            opacity: .56;
            filter: grayscale(.25);
            cursor: not-allowed;
        }

        .users-security-action__icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2rem;
            height: 2rem;
            flex: 0 0 2rem;
            border-radius: .7rem;
            background: rgba(255, 255, 255, 0.72);
        }

        .users-security-action__text {
            display: flex;
            flex-direction: column;
            gap: .08rem;
            min-width: 0;
        }

        .users-security-action__title {
            font-size: .86rem;
            font-weight: 800;
            line-height: 1.25;
        }

        .users-security-action__desc {
            font-size: .74rem;
            font-weight: 600;
            line-height: 1.35;
            opacity: .72;
        }

        .users-access-pill {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            padding: .42rem .72rem;
            border-radius: 999px;
            font-size: .8rem;
            font-weight: 700;
        }

        .users-access-pill--granted {
            color: #166534;
            background: rgba(34, 197, 94, 0.12);
            border: 1px solid rgba(34, 197, 94, 0.16);
        }

        .users-access-pill--readonly {
            color: #9a3412;
            background: rgba(251, 191, 36, 0.14);
            border: 1px solid rgba(245, 158, 11, 0.18);
        }

        .users-access-pill--current {
            color: #1d4ed8;
            background: rgba(37, 99, 235, 0.1);
            border: 1px solid rgba(37, 99, 235, 0.14);
        }

        .users-position-card__foot {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: .8rem;
            flex-wrap: wrap;
            padding-top: .85rem;
            border-top: 1px solid rgba(148, 163, 184, 0.16);
        }

        .users-position-card__actions {
            display: flex;
            gap: .5rem;
            flex-wrap: wrap;
        }

        .users-position-card__actions .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .25rem;
            border-radius: .72rem;
            font-weight: 800;
        }

        .users-status-badge {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            padding: .45rem .8rem;
            border-radius: 999px;
            font-size: .82rem;
            font-weight: 700;
        }

        .users-status-badge--active {
            color: #166534;
            background: rgba(34, 197, 94, 0.12);
            border: 1px solid rgba(34, 197, 94, 0.16);
        }

        .users-status-badge--inactive {
            color: #475569;
            background: #e2e8f0;
            border: 1px solid rgba(148, 163, 184, 0.18);
        }

        .select2-container--bootstrap-5 .select2-selection {
            min-height: 38px;
        }

        #users-table_wrapper .dataTables_filter input {
            min-width: 280px;
            border-radius: .75rem;
        }

        #users-table_wrapper .dataTables_length select {
            border-radius: .75rem;
        }

        #users-table_wrapper .dataTables_filter,
        #users-table_wrapper .dataTables_length {
            margin-bottom: 1rem;
        }

        #users-table_wrapper .dataTables_info,
        #users-table_wrapper .dataTables_paginate {
            margin-top: 1rem;
        }

        body.dark-version .users-page {
            color: #e2e8f0;
        }

        body.dark-version .users-hero {
            color: #f8fafc;
            border-color: rgba(148, 163, 184, 0.18);
            background: linear-gradient(180deg, rgba(17, 28, 68, 0.96), rgba(10, 22, 57, 0.94));
            box-shadow: 0 14px 32px rgba(0, 0, 0, 0.22);
        }

        body.dark-version .users-hero__eyebrow {
            color: #93a4bc;
        }

        body.dark-version .users-hero__desc,
        body.dark-version .users-table-toolbar p,
        body.dark-version .users-stat-card__helper,
        body.dark-version .users-table-cell__meta,
        body.dark-version .users-modal-subtitle,
        body.dark-version .users-section-card__desc,
        body.dark-version .users-position-card__subtitle,
        body.dark-version .users-position-field__helper,
        body.dark-version .users-empty-state,
        body.dark-version .users-page .text-secondary,
        body.dark-version #modalUser .text-secondary,
        body.dark-version #modalPositions .text-secondary,
        body.dark-version #modalPositionForm .text-secondary,
        body.dark-version #modalDelete .text-secondary,
        body.dark-version #modalUserSecurity .text-secondary,
        body.dark-version #modalUserSecurityReason .text-secondary,
        body.dark-version #modalUserSecurityPasswordResult .text-secondary,
        body.dark-version #modalDeactivatePosition .text-secondary,
        body.dark-version #modalHistoricalReason .text-secondary {
            color: #a8b3c7 !important;
            opacity: 1 !important;
        }

        body.dark-version .users-page .text-muted,
        body.dark-version #modalUser .text-muted,
        body.dark-version #modalPositions .text-muted,
        body.dark-version #modalPositionForm .text-muted,
        body.dark-version #modalDelete .text-muted,
        body.dark-version #modalUserSecurity .text-muted,
        body.dark-version #modalUserSecurityReason .text-muted,
        body.dark-version #modalUserSecurityPasswordResult .text-muted,
        body.dark-version #modalDeactivatePosition .text-muted,
        body.dark-version #modalHistoricalReason .text-muted {
            color: #93a4bc !important;
            opacity: 1 !important;
        }

        body.dark-version .users-chip,
        body.dark-version .users-meta-pill {
            border-color: rgba(148, 163, 184, 0.22);
            background: rgba(15, 23, 42, 0.54);
            color: #dbeafe;
        }

        body.dark-version .users-chip i {
            color: #93c5fd;
        }

        body.dark-version .users-stat-card,
        body.dark-version .users-panel,
        body.dark-version #modalUser .modal-content,
        body.dark-version #modalPositions .modal-content,
        body.dark-version #modalPositionForm .modal-content,
        body.dark-version #modalDelete .modal-content,
        body.dark-version #modalUserSecurity .modal-content,
        body.dark-version #modalUserSecurityReason .modal-content,
        body.dark-version #modalUserSecurityPasswordResult .modal-content,
        body.dark-version #modalDeactivatePosition .modal-content,
        body.dark-version #modalHistoricalReason .modal-content,
        body.dark-version .users-section-card,
        body.dark-version .users-position-card {
            border-color: rgba(148, 163, 184, 0.18);
            background: linear-gradient(180deg, rgba(17, 28, 68, 0.96), rgba(10, 22, 57, 0.94));
            box-shadow: 0 14px 32px rgba(0, 0, 0, 0.22);
        }

        body.dark-version .users-panel {
            border: 1px solid rgba(148, 163, 184, 0.18);
        }

        body.dark-version .users-stat-card__value,
        body.dark-version .users-table-toolbar h5,
        body.dark-version .users-table-cell__title,
        body.dark-version .users-modal-title,
        body.dark-version .users-section-card__title,
        body.dark-version .users-position-card__title,
        body.dark-version .users-position-field__value,
        body.dark-version .users-page .text-dark,
        body.dark-version #modalUser .text-dark,
        body.dark-version #modalPositions .text-dark,
        body.dark-version #modalPositionForm .text-dark,
        body.dark-version #modalDelete .text-dark,
        body.dark-version #modalUserSecurity .text-dark,
        body.dark-version #modalUserSecurityReason .text-dark,
        body.dark-version #modalUserSecurityPasswordResult .text-dark,
        body.dark-version #modalDeactivatePosition .text-dark,
        body.dark-version #modalHistoricalReason .text-dark {
            color: #f8fafc !important;
        }

        body.dark-version .users-stat-card__label,
        body.dark-version .users-position-field__label,
        body.dark-version .users-table thead th {
            color: #93a4bc;
        }

        body.dark-version .users-position-card__eyebrow {
            color: #93a4bc;
        }

        body.dark-version .users-position-field.users-position-field--section {
            background: transparent;
            border-color: transparent;
        }

        body.dark-version .users-position-field.users-position-field--section .users-position-field__label {
            color: #e2e8f0;
        }

        body.dark-version .users-position-field.users-position-field--section .users-position-field__label i {
            color: #93c5fd;
        }

        body.dark-version .users-inline-note,
        body.dark-version .users-helper {
            background: rgba(59, 130, 246, 0.12);
            border-color: rgba(96, 165, 250, 0.18);
            color: #cbd5e1;
        }

        body.dark-version .users-section-card__icon {
            color: #93c5fd;
            background: rgba(59, 130, 246, 0.18);
        }

        body.dark-version .users-user-modal-aside {
            background: rgba(15, 23, 42, 0.42);
            border-color: rgba(148, 163, 184, 0.18);
        }

        body.dark-version .users-user-modal-aside-title {
            color: #f8fafc;
        }

        body.dark-version .users-user-modal-aside-desc {
            color: #a8b3c7;
        }

        body.dark-version .users-user-modal-step {
            color: #dbeafe;
            background: rgba(15, 23, 42, 0.52);
            border-color: rgba(148, 163, 184, 0.18);
        }

        body.dark-version .users-user-modal-step i,
        body.dark-version .users-password-note i {
            color: #93c5fd;
        }

        body.dark-version .users-position-form-group {
            background: rgba(15, 23, 42, 0.42);
            border-color: rgba(148, 163, 184, 0.18);
        }

        body.dark-version .users-position-form-group__icon {
            color: #93c5fd;
            background: rgba(59, 130, 246, 0.18);
        }

        body.dark-version .users-position-form-group__title {
            color: #f8fafc;
        }

        body.dark-version .users-position-form-group__desc {
            color: #a8b3c7;
        }

        body.dark-version .users-position-form-stack--compact .form-label,
        body.dark-version .users-position-compact-helper {
            color: #a8b3c7;
        }

        body.dark-version .users-position-active-box {
            background: rgba(20, 184, 166, 0.14);
            border-color: rgba(45, 212, 191, 0.18);
        }

        body.dark-version .users-position-guidance {
            color: #cbd5e1;
            background: rgba(59, 130, 246, 0.12);
            border-color: rgba(96, 165, 250, 0.18);
        }

        body.dark-version .users-position-guidance i {
            color: #93c5fd;
        }

        body.dark-version .users-position-access-panel {
            background: rgba(15, 23, 42, 0.42);
            border-color: rgba(148, 163, 184, 0.18);
        }

        body.dark-version .users-year-stepper {
            background: rgba(15, 23, 42, 0.52);
            border-color: rgba(148, 163, 184, 0.2);
            box-shadow: none;
        }

        body.dark-version .users-year-stepper__btn,
        body.dark-version .users-year-stepper__input {
            background: rgba(15, 23, 42, 0.62) !important;
            color: #e2e8f0;
        }

        body.dark-version .users-year-stepper__btn:hover:not(:disabled) {
            color: #93c5fd;
            background: rgba(59, 130, 246, 0.18) !important;
        }

        body.dark-version .users-year-stepper__input {
            border-color: rgba(148, 163, 184, 0.2) !important;
        }

        body.dark-version .users-year-stepper__caption {
            color: #a8b3c7;
        }

        body.dark-version .users-year-stepper__caption i {
            color: #93c5fd;
        }

        body.dark-version .users-position-modal-chip {
            color: #dbeafe;
            background: rgba(15, 23, 42, 0.52);
            border-color: rgba(148, 163, 184, 0.18);
        }

        body.dark-version .users-active-position-panel {
            border-color: rgba(45, 212, 191, 0.2);
            background:
                linear-gradient(135deg, rgba(20, 184, 166, 0.16), rgba(59, 130, 246, 0.12)),
                rgba(15, 23, 42, 0.48);
            box-shadow: 0 14px 32px rgba(0, 0, 0, 0.22);
        }

        body.dark-version .users-active-position-panel--empty,
        body.dark-version .users-active-position-panel--loading {
            border-color: rgba(148, 163, 184, 0.18);
            background: rgba(15, 23, 42, 0.42);
        }

        body.dark-version .users-active-position-icon {
            color: #99f6e4;
            background: rgba(20, 184, 166, 0.18);
        }

        body.dark-version .users-active-position-eyebrow {
            color: #99f6e4;
        }

        body.dark-version .users-active-position-title,
        body.dark-version .users-active-position-value {
            color: #f8fafc;
        }

        body.dark-version .users-active-position-subtitle,
        body.dark-version .users-active-position-label,
        body.dark-version .users-active-position-helper {
            color: #a8b3c7;
        }

        body.dark-version .users-active-position-field {
            background: rgba(15, 23, 42, 0.52);
            border-color: rgba(148, 163, 184, 0.18);
        }

        body.dark-version .users-active-position-actions {
            border-color: rgba(148, 163, 184, 0.16);
        }

        body.dark-version .users-position-table-wrap {
            background: rgba(15, 23, 42, 0.42);
            border-color: rgba(148, 163, 184, 0.18);
        }

        body.dark-version .users-position-table {
            --bs-table-bg: transparent;
            --bs-table-color: #cbd5e1;
            color: #cbd5e1;
        }

        body.dark-version .users-position-table thead th {
            color: #93a4bc;
            background: rgba(15, 23, 42, 0.56);
            border-color: rgba(148, 163, 184, 0.16);
        }

        body.dark-version .users-position-table tbody td {
            border-color: rgba(148, 163, 184, 0.12);
        }

        body.dark-version .users-position-table tbody tr.users-position-row--active td {
            background: rgba(20, 184, 166, 0.1);
        }

        body.dark-version .users-position-table-title {
            color: #f8fafc;
        }

        body.dark-version .users-position-table-meta {
            color: #a8b3c7;
        }

        body.dark-version .users-position-table-action-btn {
            color: #e2e8f0;
            background: rgba(15, 23, 42, 0.62);
            border-color: rgba(148, 163, 184, 0.24);
        }

        body.dark-version .users-position-table-menu {
            background: #0b1637;
            border-color: rgba(148, 163, 184, 0.22);
            box-shadow: 0 16px 34px rgba(0, 0, 0, 0.3);
        }

        body.dark-version .users-position-table-menu .dropdown-item {
            color: #e2e8f0;
        }

        body.dark-version .users-position-table-menu .dropdown-item i {
            color: #93a4bc;
        }

        body.dark-version .users-position-table-menu .dropdown-item:hover {
            color: #fff;
            background: rgba(59, 130, 246, 0.18);
        }

        body.dark-version .users-filter-panel {
            background: rgba(15, 23, 42, 0.42);
            border-color: rgba(148, 163, 184, 0.2);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.16);
        }

        body.dark-version .users-filter-icon {
            color: #93c5fd;
            background: rgba(59, 130, 246, 0.18);
        }

        body.dark-version .users-filter-title {
            color: #f8fafc;
        }

        body.dark-version .users-filter-summary {
            color: #cbd5e1;
            background: rgba(148, 163, 184, 0.14);
            border-color: rgba(148, 163, 184, 0.18);
        }

        body.dark-version .users-filter-group {
            border-color: rgba(148, 163, 184, 0.16);
        }

        body.dark-version .users-filter-group__header {
            color: #cbd5e1;
        }

        body.dark-version .users-filter-group__header i {
            color: #93c5fd;
        }

        body.dark-version .users-filter-field {
            background: rgba(15, 23, 42, 0.42);
            border-color: rgba(148, 163, 184, 0.16);
        }

        body.dark-version .users-filter-panel .form-label {
            color: #cbd5e1;
        }

        body.dark-version .users-filter-chips {
            border-color: rgba(148, 163, 184, 0.16);
        }

        body.dark-version .users-active-filter-chip {
            border-color: rgba(96, 165, 250, 0.2);
            color: #dbeafe;
            background: rgba(59, 130, 246, 0.16);
        }

        body.dark-version .users-active-filter-chip button {
            color: #bfdbfe;
            background: rgba(15, 23, 42, 0.56);
        }

        body.dark-version .users-active-filter-chip button:hover {
            color: #fff;
            background: #2563eb;
        }

        body.dark-version .users-danger-note {
            background: rgba(239, 68, 68, 0.14);
            border-color: rgba(248, 113, 113, 0.22);
            color: #fecaca;
        }

        body.dark-version .users-security-reason-alert,
        body.dark-version .users-admin-reason-alert {
            color: #fde68a;
            background: rgba(245, 158, 11, 0.14);
            border-color: rgba(251, 191, 36, 0.22);
        }

        body.dark-version .users-security-reason-field,
        body.dark-version .users-admin-reason-field {
            background: rgba(15, 23, 42, 0.42);
            border-color: rgba(148, 163, 184, 0.18);
        }

        body.dark-version .users-security-reason-audit,
        body.dark-version .users-admin-reason-audit {
            color: #bfdbfe;
            background: rgba(59, 130, 246, 0.12);
            border-color: rgba(96, 165, 250, 0.18);
        }

        body.dark-version .users-historical-context__item,
        body.dark-version .users-historical-card {
            background: rgba(15, 23, 42, 0.48);
            border-color: rgba(148, 163, 184, 0.18);
        }

        body.dark-version .users-historical-context__label,
        body.dark-version .users-historical-card__desc,
        body.dark-version .users-historical-metadata-grid .form-label {
            color: #a8b3c7;
        }

        body.dark-version .users-historical-context__label i,
        body.dark-version .users-historical-card__title i,
        body.dark-version .users-historical-audit-note i {
            color: #93c5fd;
        }

        body.dark-version .users-historical-context__value,
        body.dark-version .users-historical-card__title {
            color: #f8fafc;
        }

        body.dark-version .users-historical-required {
            color: #fde68a;
            background: rgba(245, 158, 11, 0.15);
            border-color: rgba(251, 191, 36, 0.2);
        }

        body.dark-version .users-historical-audit-note {
            color: #bfdbfe;
            background: rgba(59, 130, 246, 0.12);
            border-color: rgba(96, 165, 250, 0.18);
        }

        body.dark-version .users-temporary-password-box {
            background: rgba(59, 130, 246, 0.12);
            border-color: rgba(96, 165, 250, 0.18);
        }

        body.dark-version .users-temporary-password-card {
            background: rgba(15, 23, 42, 0.48);
            border-color: rgba(148, 163, 184, 0.18);
        }

        body.dark-version .users-temporary-password-card__title {
            color: #cbd5e1;
        }

        body.dark-version .users-temporary-password-card__title i {
            color: #93c5fd;
        }

        body.dark-version .users-temporary-password-status {
            color: #fde68a;
            background: rgba(245, 158, 11, 0.15);
            border-color: rgba(251, 191, 36, 0.2);
        }

        body.dark-version .users-temporary-password-status--copied {
            color: #bbf7d0;
            background: rgba(34, 197, 94, 0.15);
            border-color: rgba(74, 222, 128, 0.2);
        }

        body.dark-version .users-temporary-password-value {
            background: rgba(15, 23, 42, 0.52);
            border-color: rgba(96, 165, 250, 0.28);
            color: #f8fafc;
        }

        body.dark-version .users-temporary-password-note,
        body.dark-version .users-temporary-password-checkitem {
            color: #a8b3c7;
        }

        body.dark-version .users-position-field,
        body.dark-version .users-empty-state i {
            background: rgba(15, 23, 42, 0.52);
            border-color: rgba(148, 163, 184, 0.18);
        }

        body.dark-version .users-empty-state i {
            color: #93c5fd;
        }

        body.dark-version .users-count-pill {
            color: #99f6e4;
            background: rgba(20, 184, 166, 0.18);
            border: 1px solid rgba(45, 212, 191, 0.18);
        }

        body.dark-version .users-account-badge--active,
        body.dark-version .users-position-pill--active,
        body.dark-version .users-position-pill--used,
        body.dark-version .users-security-badge--ok {
            color: #bbf7d0;
            background: rgba(34, 197, 94, 0.16);
            border-color: rgba(74, 222, 128, 0.2);
        }

        body.dark-version .users-account-badge--pending,
        body.dark-version .users-position-pill--missing,
        body.dark-version .users-security-badge--password-required {
            color: #fde68a;
            background: rgba(245, 158, 11, 0.16);
            border-color: rgba(251, 191, 36, 0.2);
        }

        body.dark-version .users-account-badge--locked,
        body.dark-version .users-account-badge--suspended,
        body.dark-version .users-position-pill--inactive,
        body.dark-version .users-security-badge--locked {
            color: #fecaca;
            background: rgba(239, 68, 68, 0.16);
            border-color: rgba(248, 113, 113, 0.22);
        }

        body.dark-version .users-type-badge {
            color: #dbeafe;
            background: rgba(59, 130, 246, 0.16);
            border-color: rgba(96, 165, 250, 0.2);
        }

        body.dark-version .users-type-badge--functional {
            color: #ddd6fe;
            background: rgba(139, 92, 246, 0.18);
            border-color: rgba(167, 139, 250, 0.22);
        }

        body.dark-version .users-type-badge--service {
            color: #99f6e4;
            background: rgba(20, 184, 166, 0.18);
            border-color: rgba(45, 212, 191, 0.2);
        }

        body.dark-version .users-type-badge--emergency {
            color: #fed7aa;
            background: rgba(249, 115, 22, 0.18);
            border-color: rgba(251, 146, 60, 0.22);
        }

        body.dark-version .users-year-pill {
            color: #cbd5e1;
            background: rgba(15, 23, 42, 0.52);
            border-color: rgba(148, 163, 184, 0.18);
        }

        body.dark-version .users-muted-pill,
        body.dark-version .users-status-badge--inactive {
            color: #cbd5e1;
            background: rgba(148, 163, 184, 0.16);
            border-color: rgba(148, 163, 184, 0.18);
        }

        body.dark-version .users-account-badge--inactive {
            color: #cbd5e1;
            background: rgba(148, 163, 184, 0.16);
            border-color: rgba(148, 163, 184, 0.18);
        }

        body.dark-version .users-position-pill--history,
        body.dark-version .users-security-badge--mfa-missing {
            color: #cbd5e1;
            background: rgba(148, 163, 184, 0.16);
            border-color: rgba(148, 163, 184, 0.18);
        }

        body.dark-version .users-security-badge--password-ok {
            color: #dbeafe;
            background: rgba(59, 130, 246, 0.16);
            border-color: rgba(96, 165, 250, 0.2);
        }

        body.dark-version .users-position-pill--unused {
            color: #dbeafe;
            background: rgba(59, 130, 246, 0.16);
            border-color: rgba(96, 165, 250, 0.2);
        }

        body.dark-version .users-security-badge--mfa-active {
            color: #99f6e4;
            background: rgba(20, 184, 166, 0.18);
            border-color: rgba(45, 212, 191, 0.2);
        }

        body.dark-version .users-security-badge--mfa-pending {
            color: #ddd6fe;
            background: rgba(139, 92, 246, 0.18);
            border-color: rgba(167, 139, 250, 0.22);
        }

        body.dark-version .users-action-menu-btn {
            color: #e2e8f0;
            background: rgba(15, 23, 42, 0.62);
            border-color: rgba(148, 163, 184, 0.24);
        }

        body.dark-version .users-action-menu {
            background: #0b1637;
            border-color: rgba(148, 163, 184, 0.22);
            box-shadow: 0 16px 34px rgba(0, 0, 0, 0.3);
        }

        body.dark-version .users-action-menu .dropdown-item {
            color: #e2e8f0;
        }

        body.dark-version .users-action-menu .dropdown-item i {
            color: #93a4bc;
        }

        body.dark-version .users-action-menu .dropdown-item:hover {
            color: #fff;
            background: rgba(59, 130, 246, 0.18);
        }

        body.dark-version .users-position-card__foot {
            border-color: rgba(148, 163, 184, 0.16);
        }

        body.dark-version .users-access-pill--granted,
        body.dark-version .users-status-badge--active {
            color: #bbf7d0;
            background: rgba(34, 197, 94, 0.16);
            border-color: rgba(74, 222, 128, 0.2);
        }

        body.dark-version .users-access-pill--readonly {
            color: #fde68a;
            background: rgba(251, 191, 36, 0.16);
            border-color: rgba(251, 191, 36, 0.24);
        }

        body.dark-version .users-access-pill--current {
            color: #bfdbfe;
            background: rgba(37, 99, 235, 0.18);
            border-color: rgba(96, 165, 250, 0.22);
        }

        body.dark-version #modalUser .modal-header,
        body.dark-version #modalPositions .modal-header,
        body.dark-version #modalPositionForm .modal-header,
        body.dark-version #modalDelete .modal-header,
        body.dark-version #modalUserSecurity .modal-header,
        body.dark-version #modalUserSecurityReason .modal-header,
        body.dark-version #modalUserSecurityPasswordResult .modal-header,
        body.dark-version #modalDeactivatePosition .modal-header,
        body.dark-version #modalHistoricalReason .modal-header,
        body.dark-version #modalUser .modal-footer,
        body.dark-version #modalPositions .modal-footer,
        body.dark-version #modalPositionForm .modal-footer,
        body.dark-version #modalDelete .modal-footer,
        body.dark-version #modalUserSecurity .modal-footer,
        body.dark-version #modalUserSecurityReason .modal-footer,
        body.dark-version #modalUserSecurityPasswordResult .modal-footer,
        body.dark-version #modalDeactivatePosition .modal-footer,
        body.dark-version #modalHistoricalReason .modal-footer {
            border-color: rgba(148, 163, 184, 0.16);
        }

        body.dark-version #modalUser .btn-close,
        body.dark-version #modalPositions .btn-close,
        body.dark-version #modalPositionForm .btn-close,
        body.dark-version #modalDelete .btn-close,
        body.dark-version #modalUserSecurity .btn-close,
        body.dark-version #modalUserSecurityReason .btn-close,
        body.dark-version #modalUserSecurityPasswordResult .btn-close,
        body.dark-version #modalDeactivatePosition .btn-close,
        body.dark-version #modalHistoricalReason .btn-close {
            filter: invert(1) grayscale(100%) brightness(180%);
        }

        body.dark-version .users-page .table,
        body.dark-version .users-page .dataTable {
            --bs-table-bg: transparent;
            --bs-table-color: #cbd5e1;
            --bs-table-striped-bg: rgba(15, 23, 42, 0.45);
            --bs-table-striped-color: #e2e8f0;
            --bs-table-hover-bg: rgba(56, 189, 248, 0.08);
            color: #cbd5e1;
        }

        body.dark-version .users-page .table-light,
        body.dark-version .users-page .table-light > * {
            --bs-table-bg: #0b1637;
            --bs-table-color: #93a4bc;
            background-color: #0b1637 !important;
            color: #93a4bc !important;
        }

        body.dark-version .users-page .table > :not(caption) > * > * {
            border-color: rgba(148, 163, 184, 0.16) !important;
            color: inherit !important;
        }

        body.dark-version .users-page .dt-container,
        body.dark-version .users-page .dt-info,
        body.dark-version .users-page .dt-length label,
        body.dark-version .users-page .dt-search label,
        body.dark-version .users-page .dataTables_info,
        body.dark-version .users-page .dataTables_length label,
        body.dark-version .users-page .dataTables_filter label {
            color: #a8b3c7 !important;
        }

        body.dark-version .users-page .dt-input,
        body.dark-version .users-page .dt-search input,
        body.dark-version .users-page .dt-length select,
        body.dark-version .users-page .dataTables_filter input,
        body.dark-version .users-page .dataTables_length select,
        body.dark-version #modalUser .form-control,
        body.dark-version #modalUser .form-select,
        body.dark-version #modalPositions .form-control,
        body.dark-version #modalPositions .form-select,
        body.dark-version #modalPositionForm .form-control,
        body.dark-version #modalPositionForm .form-select,
        body.dark-version #modalDelete .form-control,
        body.dark-version #modalDelete .form-select,
        body.dark-version #modalUserSecurity .form-control,
        body.dark-version #modalUserSecurity .form-select,
        body.dark-version #modalUserSecurityReason .form-control,
        body.dark-version #modalUserSecurityReason .form-select,
        body.dark-version #modalUserSecurityPasswordResult .form-control,
        body.dark-version #modalUserSecurityPasswordResult .form-select,
        body.dark-version #modalDeactivatePosition .form-control,
        body.dark-version #modalDeactivatePosition .form-select,
        body.dark-version #modalHistoricalReason .form-control,
        body.dark-version #modalHistoricalReason .form-select {
            background-color: #0b1637 !important;
            border-color: rgba(148, 163, 184, 0.28) !important;
            color: #e2e8f0 !important;
        }

        body.dark-version #modalUser .form-control::placeholder,
        body.dark-version #modalPositions .form-control::placeholder,
        body.dark-version #modalPositionForm .form-control::placeholder,
        body.dark-version #modalUserSecurity .form-control::placeholder,
        body.dark-version #modalUserSecurityReason .form-control::placeholder,
        body.dark-version #modalDeactivatePosition .form-control::placeholder,
        body.dark-version #modalHistoricalReason .form-control::placeholder {
            color: #64748b;
        }

        body.dark-version .users-page .dt-input:focus,
        body.dark-version .users-page .dt-search input:focus,
        body.dark-version .users-page .dataTables_filter input:focus,
        body.dark-version #modalUser .form-control:focus,
        body.dark-version #modalUser .form-select:focus,
        body.dark-version #modalPositions .form-control:focus,
        body.dark-version #modalPositions .form-select:focus,
        body.dark-version #modalPositionForm .form-control:focus,
        body.dark-version #modalPositionForm .form-select:focus,
        body.dark-version #modalUserSecurity .form-control:focus,
        body.dark-version #modalUserSecurity .form-select:focus,
        body.dark-version #modalUserSecurityReason .form-control:focus,
        body.dark-version #modalUserSecurityReason .form-select:focus,
        body.dark-version #modalDeactivatePosition .form-control:focus,
        body.dark-version #modalDeactivatePosition .form-select:focus,
        body.dark-version #modalHistoricalReason .form-control:focus,
        body.dark-version #modalHistoricalReason .form-select:focus {
            border-color: rgba(96, 165, 250, 0.82) !important;
            box-shadow: 0 0 0 .2rem rgba(59, 130, 246, 0.18);
        }

        body.dark-version .users-page .page-link {
            background-color: #0b1637;
            border-color: rgba(148, 163, 184, 0.18);
            color: #cbd5e1;
        }

        body.dark-version .users-page .page-item.active .page-link {
            background: #2563eb;
            border-color: #2563eb;
            color: #fff;
        }

        body.dark-version .users-page .page-item.disabled .page-link {
            background-color: rgba(15, 23, 42, 0.52);
            color: #64748b;
        }

        body.dark-version .users-page .dataTables_processing,
        body.dark-version .users-page .dt-processing {
            background: rgba(17, 28, 68, 0.94);
            border: 1px solid rgba(148, 163, 184, 0.18);
            color: #e2e8f0;
            box-shadow: 0 16px 32px rgba(0, 0, 0, 0.24);
        }

        body.dark-version .select2-container--bootstrap-5 .select2-selection,
        body.dark-version .select2-container--bootstrap-5 .select2-dropdown {
            background-color: #0b1637;
            border-color: rgba(148, 163, 184, 0.28);
            color: #e2e8f0;
        }

        body.dark-version .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered,
        body.dark-version .select2-container--bootstrap-5 .select2-selection__clear,
        body.dark-version .select2-container--bootstrap-5 .select2-results__option {
            color: #e2e8f0;
        }

        body.dark-version .select2-container--bootstrap-5 .select2-selection__placeholder {
            color: #94a3b8;
        }

        body.dark-version .select2-container--bootstrap-5 .select2-search__field {
            background: #111c44;
            border-color: rgba(148, 163, 184, 0.28);
            color: #e2e8f0;
        }

        body.dark-version .select2-container--bootstrap-5 .select2-results__option--selected,
        body.dark-version .select2-container--bootstrap-5 .select2-results__option[aria-selected=true] {
            background: rgba(59, 130, 246, 0.18);
            color: #f8fafc;
        }

        body.dark-version .select2-container--bootstrap-5 .select2-results__option--highlighted,
        body.dark-version .select2-container--bootstrap-5 .select2-results__option--highlighted.select2-results__option--selectable {
            background: #2563eb;
            color: #fff;
        }

        body.dark-version #modalUser .btn-light,
        body.dark-version #modalPositions .btn-light,
        body.dark-version #modalPositionForm .btn-light,
        body.dark-version #modalDelete .btn-light,
        body.dark-version #modalUserSecurity .btn-light,
        body.dark-version #modalUserSecurityReason .btn-light,
        body.dark-version #modalUserSecurityPasswordResult .btn-light,
        body.dark-version #modalDeactivatePosition .btn-light,
        body.dark-version #modalHistoricalReason .btn-light {
            background: rgba(15, 23, 42, 0.62);
            border-color: rgba(148, 163, 184, 0.24) !important;
            color: #e2e8f0;
        }

        body.dark-version .users-security-overview {
            border-color: rgba(96, 165, 250, 0.18);
            background:
                linear-gradient(135deg, rgba(59, 130, 246, 0.16), rgba(20, 184, 166, 0.1)),
                rgba(15, 23, 42, 0.64);
        }

        body.dark-version .users-security-overview__title {
            color: #f8fafc;
        }

        body.dark-version .users-security-overview__desc {
            color: #a8b3c7;
        }

        body.dark-version .users-security-overview__meta {
            color: #bfdbfe;
            background: rgba(59, 130, 246, 0.16);
            border-color: rgba(96, 165, 250, 0.2);
        }

        body.dark-version .users-security-signal {
            background: rgba(15, 23, 42, 0.48);
            border-color: rgba(148, 163, 184, 0.18);
        }

        body.dark-version .users-security-signal__label {
            color: #93a4bc;
        }

        body.dark-version .users-security-signal__value {
            color: #f8fafc;
        }

        body.dark-version .users-security-signal--success {
            background: rgba(34, 197, 94, 0.12);
            border-color: rgba(74, 222, 128, 0.18);
        }

        body.dark-version .users-security-signal--success .users-security-signal__icon {
            color: #bbf7d0;
            background: rgba(34, 197, 94, 0.16);
        }

        body.dark-version .users-security-signal--warning {
            background: rgba(245, 158, 11, 0.13);
            border-color: rgba(251, 191, 36, 0.18);
        }

        body.dark-version .users-security-signal--warning .users-security-signal__icon {
            color: #fde68a;
            background: rgba(245, 158, 11, 0.16);
        }

        body.dark-version .users-security-signal--danger {
            background: rgba(239, 68, 68, 0.13);
            border-color: rgba(248, 113, 113, 0.2);
        }

        body.dark-version .users-security-signal--danger .users-security-signal__icon {
            color: #fecaca;
            background: rgba(239, 68, 68, 0.16);
        }

        body.dark-version .users-security-signal--muted .users-security-signal__icon {
            color: #cbd5e1;
            background: rgba(148, 163, 184, 0.16);
        }

        body.dark-version .users-security-action-group {
            border-color: rgba(148, 163, 184, 0.16);
        }

        body.dark-version .users-security-action-group__header {
            color: #cbd5e1;
        }

        body.dark-version .users-security-action-group__header i {
            color: #93c5fd;
        }

        body.dark-version .users-security-action__icon {
            background: rgba(15, 23, 42, 0.44);
        }

        @media (max-width: 991.98px) {
            .users-hero,
            .users-table-toolbar {
                flex-direction: column;
            }

            .users-position-modal-heading,
            .users-active-position-top {
                flex-direction: column;
            }

            .users-position-modal-meta {
                justify-content: flex-start;
                max-width: 100%;
            }

            .users-active-position-badges {
                justify-content: flex-start;
            }

            .users-active-position-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .users-filter-header {
                align-items: stretch;
                flex-direction: column;
            }

            .users-filter-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .users-filter-actions {
                justify-content: flex-start;
            }

            .users-user-modal-grid {
                grid-template-columns: 1fr;
            }

            .users-hero__cta {
                width: 100%;
            }

            .users-hero__cta .btn {
                width: 100%;
            }

            .users-position-card__meta {
                grid-template-columns: 1fr;
            }

            .users-position-card__head {
                flex-direction: column;
            }

            .users-position-card__badges {
                justify-content: flex-start;
            }

            .users-security-grid,
            .users-security-signal-grid,
            .users-security-actions {
                grid-template-columns: 1fr;
            }

            .users-historical-context {
                grid-template-columns: 1fr;
            }

            .users-security-overview {
                align-items: flex-start;
                flex-direction: column;
            }
        }

        @media (max-width: 575.98px) {
            .users-filter-panel {
                padding: .85rem;
            }

            .users-position-modal-chip {
                width: 100%;
            }

            .users-position-modal-chip span {
                white-space: normal;
            }

            .users-active-position-panel {
                padding: .85rem;
            }

            .users-active-position-grid {
                grid-template-columns: 1fr;
            }

            .users-active-position-actions {
                align-items: stretch;
                flex-direction: column;
            }

            .users-active-position-actions .btn,
            .users-active-position-actions .users-muted-pill {
                width: 100%;
                justify-content: center;
            }

            .users-position-form-actions {
                align-items: stretch;
                flex-direction: column;
            }

            .users-position-form-actions .btn {
                width: 100%;
            }

            .users-filter-group {
                padding: .72rem;
            }

            .users-filter-grid {
                grid-template-columns: 1fr;
            }

            .users-filter-actions {
                align-items: stretch;
                flex-direction: column;
            }

            .users-filter-actions .btn {
                width: 100%;
            }

            .users-security-overview {
                padding: .85rem;
            }

            .users-security-action {
                align-items: flex-start;
                min-height: auto;
            }

            .users-temporary-password-box {
                flex-direction: column;
            }

            .users-temporary-password-copy {
                width: 100%;
            }

            .users-historical-heading,
            .users-historical-card__header {
                flex-direction: column;
            }

            .users-historical-required {
                align-self: flex-start;
            }

            .users-historical-metadata-grid {
                grid-template-columns: 1fr;
            }

            .users-security-reason-alert,
            .users-admin-reason-alert {
                gap: .7rem;
                padding: .85rem;
            }

            .users-security-reason-field,
            .users-admin-reason-field {
                padding: .85rem;
            }
        }
    </style>
@endsection

@section('content')
    <div class="users-page">
        <div class="users-hero">
            <div>
                <span class="users-hero__eyebrow">
                    <i class="fa fa-users-gear"></i> Management Users
                </span>
                <h2 class="users-hero__title">Pengaturan Pengguna</h2>
                <p class="users-hero__desc">
                    Kelola identitas akun, posisi jabatan, keamanan akses, dan audit administrasi pengguna.
                </p>
                <div class="users-hero__chips">
                    <span class="users-chip">
                        <i class="fa fa-shield-halved"></i>
                        {{ $userManagementContext['scope_label'] ?? 'Scope aktif' }}
                    </span>
                    <span class="users-chip"><i class="fa fa-table"></i> DataTables server-side</span>
                    <span class="users-chip"><i class="fa fa-clock-rotate-left"></i> Audit aktif</span>
                </div>
            </div>
            <div class="users-hero__cta d-flex flex-wrap gap-2 justify-content-end">
                <a href="{{ route('users.audit-trail') }}" class="btn btn-outline-primary px-3 mb-0">
                    <i class="fa fa-clipboard-list me-2"></i>Audit Trail
                </a>
                <button class="btn bg-gradient-success px-3 mb-0" id="btnCreateUser">
                    <i class="fa fa-user-plus me-2"></i>Tambah User
                </button>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-md-6 col-xl-3">
                <div class="card users-stat-card">
                    <div class="card-body">
                        <div class="users-stat-card__content">
                            <div class="users-stat-card__label">Total User</div>
                            <div class="users-stat-card__value" id="metricTotalUsers">0</div>
                            <div class="users-stat-card__helper">Seluruh akun tercatat.</div>
                        </div>
                        <span class="users-stat-card__icon users-stat-card__icon--primary">
                            <i class="fa fa-users"></i>
                        </span>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="card users-stat-card">
                    <div class="card-body">
                        <div class="users-stat-card__content">
                            <div class="users-stat-card__label">Hasil Tersaring</div>
                            <div class="users-stat-card__value" id="metricFilteredUsers">0</div>
                            <div class="users-stat-card__helper" id="metricFilteredHelper">Belum ada filter aktif.</div>
                        </div>
                        <span class="users-stat-card__icon users-stat-card__icon--success">
                            <i class="fa fa-filter"></i>
                        </span>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="card users-stat-card">
                    <div class="card-body">
                        <div class="users-stat-card__content">
                            <div class="users-stat-card__label">Posisi Halaman Ini</div>
                            <div class="users-stat-card__value" id="metricPagePositions">0</div>
                            <div class="users-stat-card__helper">Akumulasi baris tampil.</div>
                        </div>
                        <span class="users-stat-card__icon users-stat-card__icon--info">
                            <i class="fa fa-briefcase"></i>
                        </span>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="card users-stat-card">
                    <div class="card-body">
                        <div class="users-stat-card__content">
                            <div class="users-stat-card__label">Mode Tampilan</div>
                            <div class="users-stat-card__value fs-5" id="metricSearchState">Semua User</div>
                            <div class="users-stat-card__helper" id="metricRangeHelper">Menunggu data dimuat.</div>
                        </div>
                        <span class="users-stat-card__icon users-stat-card__icon--dark">
                            <i class="fa fa-chart-simple"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="card users-panel">
            <div class="card-body p-0">
                <div class="users-table-toolbar">
                    <div>
                        <h5>Daftar User</h5>
                        <p>Cari berdasarkan nama, NIK, NIP, atau email. Gunakan aksi posisi untuk mengelola jabatan,
                            file SK, dan status posisi aktif.</p>
                    </div>
                </div>

                <div class="users-inline-note">
                    Klik <strong>Posisi</strong> untuk menambahkan jabatan baru, menetapkan masa berlaku SK, dan
                    mengganti posisi aktif tanpa perlu keluar dari halaman ini.
                </div>

                <div class="users-filter-panel" id="usersFilterPanel">
                    <div class="users-filter-header">
                        <div class="users-filter-heading">
                            <span class="users-filter-icon">
                                <i class="fa fa-sliders"></i>
                            </span>
                            <div>
                                <div class="users-filter-title">Filter Data</div>
                                <div class="users-filter-summary" id="usersFilterSummary">Semua user</div>
                            </div>
                        </div>
                        <div class="users-filter-actions">
                            <button type="button" class="btn btn-primary btn-sm" id="btnApplyUserFilters">
                                <i class="fa fa-filter me-1"></i> Terapkan
                            </button>
                            <button type="button" class="btn btn-light btn-sm d-none" id="btnResetUserFilters">
                                <i class="fa fa-rotate-left me-1"></i> Reset
                            </button>
                        </div>
                    </div>
                    <div class="users-filter-body">
                        <div class="users-filter-group">
                            <div class="users-filter-group__header">
                                <i class="fa fa-user-shield"></i>
                                <span>Akun</span>
                            </div>
                            <div class="users-filter-grid">
                                <div class="users-filter-field">
                                    <label class="form-label" for="filter_status">Status Akun</label>
                                    <select id="filter_status" class="form-select select2-basic"
                                        data-placeholder="Semua status">
                                        <option value=""></option>
                                        @foreach ($userAccountStatuses as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="users-filter-field">
                                    <label class="form-label" for="filter_account_type">Tipe Akun</label>
                                    <select id="filter_account_type" class="form-select select2-basic"
                                        data-placeholder="Semua tipe">
                                        <option value=""></option>
                                        @foreach ($userAccountTypes as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="users-filter-field">
                                    <label class="form-label" for="filter_position_state">Status Posisi</label>
                                    <select id="filter_position_state" class="form-select select2-basic"
                                        data-placeholder="Semua posisi">
                                        <option value=""></option>
                                        <option value="has_positions">Sudah punya posisi</option>
                                        <option value="no_positions">Belum punya posisi</option>
                                        <option value="has_active_position">Punya posisi aktif</option>
                                        <option value="no_active_position">Tidak punya posisi aktif</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="users-filter-group">
                            <div class="users-filter-group__header">
                                <i class="fa fa-sitemap"></i>
                                <span>Posisi & Scope</span>
                            </div>
                            <div class="users-filter-grid">
                                <div class="users-filter-field">
                                    <label class="form-label" for="filter_jabatan_id">Jabatan</label>
                                    <select id="filter_jabatan_id" class="form-select select2-basic"
                                        data-placeholder="Semua jabatan">
                                        <option value=""></option>
                                        @foreach ($jabatans as $jabatan)
                                            <option value="{{ \App\Support\EncryptedId::encode($jabatan->id) }}">
                                                {{ $jabatan->nama }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="users-filter-field">
                                    <label class="form-label" for="filter_instansi_id">Instansi</label>
                                    <select id="filter_instansi_id" class="form-select select2-basic"
                                        data-placeholder="Semua instansi" disabled>
                                        <option value=""></option>
                                    </select>
                                </div>
                                <div class="users-filter-field">
                                    <label class="form-label" for="filter_unit_kerja_id">Unit Kerja</label>
                                    <select id="filter_unit_kerja_id" class="form-select select2-basic"
                                        data-placeholder="Semua unit kerja" disabled>
                                        <option value=""></option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="users-filter-chips d-none" id="usersActiveFilterChips"></div>
                </div>

                <div class="users-table-wrap">
                    <table id="users-table" class="table table-striped table-bordered align-middle users-table nowrap"
                        style="width:100%;">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Identitas</th>
                                <th>Nama User</th>
                                <th>Email</th>
                                <th>Status</th>
                                <th>Keamanan</th>
                                <th>Jabatan Dipakai</th>
                                <th>Jumlah Posisi</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalUser" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <form class="modal-content" id="formUser">
                @csrf
                <div class="modal-header users-modal-header">
                    <div>
                        <h5 class="modal-title users-modal-title" id="titleUser">Tambah User</h5>
                        <p class="users-modal-subtitle" id="subtitleUser">Buat profil akun terlebih dahulu, lalu
                            lanjutkan pengaturan posisi user.</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="users-user-modal-grid">
                        <aside class="users-user-modal-aside">
                            <span class="users-user-modal-avatar">
                                <i class="fa fa-user-gear"></i>
                            </span>
                            <div class="users-user-modal-aside-title">Profil Akun</div>
                            <p class="users-user-modal-aside-desc">
                                Akun dibuat terlebih dahulu, lalu posisi jabatan dikelola setelah profil tersimpan.
                            </p>
                            <div class="users-user-modal-steps">
                                <div class="users-user-modal-step">
                                    <i class="fa fa-id-card"></i>
                                    <span>Identitas</span>
                                </div>
                                <div class="users-user-modal-step">
                                    <i class="fa fa-sliders"></i>
                                    <span>Status Akun</span>
                                </div>
                                <div class="users-user-modal-step">
                                    <i class="fa fa-key"></i>
                                    <span>Akses Masuk</span>
                                </div>
                            </div>
                        </aside>

                        <div class="users-user-form-stack">
                            <div class="users-section-card">
                                <div class="users-section-card__header">
                                    <div class="users-section-card__heading">
                                        <span class="users-section-card__icon">
                                            <i class="fa fa-address-card"></i>
                                        </span>
                                        <div>
                                            <h6 class="users-section-card__title">Identitas Dasar</h6>
                                            <p class="users-section-card__desc">Data utama untuk pencarian dan verifikasi
                                                user.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="users-section-card__body">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">NIK</label>
                                            <input class="form-control" name="nik" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">NIP</label>
                                            <input class="form-control" name="nip">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Nama Lengkap</label>
                                            <input class="form-control" name="nama" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Email</label>
                                            <input class="form-control" name="email" type="email"
                                                placeholder="contoh@instansi.go.id">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="users-section-card">
                                <div class="users-section-card__header">
                                    <div class="users-section-card__heading">
                                        <span class="users-section-card__icon">
                                            <i class="fa fa-toggle-on"></i>
                                        </span>
                                        <div>
                                            <h6 class="users-section-card__title">Status & Preferensi Akun</h6>
                                            <p class="users-section-card__desc">Tipe akun, status operasional, dan tahun
                                                aktif default.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="users-section-card__body">
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label class="form-label">Tipe Akun</label>
                                            <select name="account_type" id="user_account_type"
                                                class="form-select select2-basic" required>
                                                @foreach ($userAccountTypes as $value => $label)
                                                    <option value="{{ $value }}" @selected($value === \App\Models\User::ACCOUNT_TYPE_PERSONAL)>
                                                        {{ $label }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Status Akun</label>
                                            <select name="status" id="user_status" class="form-select select2-basic"
                                                required>
                                                @foreach ($editableUserAccountStatuses as $value => $label)
                                                    <option value="{{ $value }}" @selected($value === \App\Models\User::STATUS_ACTIVE)>
                                                        {{ $label }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <div class="users-helper mt-2 d-none" id="userLockedStatusHint">
                                                Status terkunci hanya bisa diubah melalui menu Keamanan Akun agar audit,
                                                sesi, dan reason lock/unlock tetap konsisten.
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Tahun Aktif Default</label>
                                            <input class="form-control" type="number" name="tahun_aktif"
                                                id="user_tahun_aktif" min="2000" max="2100"
                                                value="{{ (int) session('tahun_aktif', now()->year) }}">
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label">Alasan Status</label>
                                            <textarea class="form-control" name="status_reason" id="user_status_reason" rows="2" maxlength="500"
                                                placeholder="Wajib diisi saat status akun bukan aktif."></textarea>
                                            <div class="users-helper mt-2 d-none" id="userStatusReasonHint">
                                                Jelaskan alasan status nonaktif, pending, atau suspended.
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="users-section-card">
                                <div class="users-section-card__header">
                                    <div class="users-section-card__heading">
                                        <span class="users-section-card__icon">
                                            <i class="fa fa-lock"></i>
                                        </span>
                                        <div>
                                            <h6 class="users-section-card__title">Akses Masuk</h6>
                                            <p class="users-section-card__desc">Password akun dan konfirmasi keamanan
                                                perubahan akses.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="users-section-card__body">
                                    <div class="row g-3 align-items-stretch">
                                        <div class="col-md-6 d-none" id="oldPasswordWrap">
                                            <label class="form-label">Password Lama Anda</label>
                                            <input class="form-control" name="old_password" type="password"
                                                autocomplete="current-password">
                                            <div class="users-helper mt-2">
                                                Isi password akun Anda yang sedang login saat ingin mengganti password user
                                                ini.
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Password <small class="text-muted"
                                                    id="hintPass"></small></label>
                                            <input class="form-control" name="password" type="password"
                                                autocomplete="new-password">
                                        </div>
                                        <div class="col-md-6">
                                            <div class="users-helper users-password-note">
                                                <i class="fa fa-circle-info"></i>
                                                <span>Password minimal 8 karakter dan berisi huruf besar, huruf kecil,
                                                    angka, serta simbol.</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-light border" data-bs-dismiss="modal" type="button">Batal</button>
                    <button class="btn btn-primary" type="submit">
                        <i class="fa fa-floppy-disk me-1"></i>Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="modalPositions" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header users-modal-header">
                    <div class="users-position-modal-heading">
                        <div class="users-position-modal-identity">
                            <span class="users-position-modal-avatar">
                                <i class="fa fa-user-gear"></i>
                            </span>
                            <div class="users-position-modal-copy">
                                <h5 class="modal-title users-modal-title">
                                    Kelola Posisi User
                                </h5>
                                <p class="users-modal-subtitle mb-0">
                                    <span id="posUserName" class="fw-semibold text-dark"></span>
                                    <span class="text-muted">dikelola melalui posisi, scope kerja, dan dokumen SK.</span>
                                </p>
                                <div class="users-position-summary">
                                    <span class="users-meta-pill" id="posCount">0 posisi</span>
                                    <span class="users-meta-pill" id="posActiveLabel">Belum ada posisi aktif</span>
                                    <span class="users-meta-pill" id="posYearAccessLabel">Tahun aktif belum dibaca</span>
                                </div>
                            </div>
                        </div>
                        <div class="users-position-modal-meta">
                            <span class="users-position-modal-chip">
                                <i class="fa fa-id-card"></i>
                                <span id="posHeaderNik">NIK -</span>
                            </span>
                            <span class="users-position-modal-chip">
                                <i class="fa fa-envelope"></i>
                                <span id="posHeaderEmail">Email -</span>
                            </span>
                            <span class="users-position-modal-chip">
                                <i class="fa fa-user-tag"></i>
                                <span id="posHeaderAccountType">Tipe akun -</span>
                            </span>
                            <span class="users-position-modal-chip">
                                <i class="fa fa-circle-check" id="posHeaderStatusIcon"></i>
                                <span id="posHeaderStatus">Status -</span>
                            </span>
                            <span class="users-position-modal-chip">
                                <i class="fa fa-calendar-days"></i>
                                <span id="posHeaderYear">Tahun -</span>
                            </span>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="users-position-guidance">
                        <i class="fa fa-circle-info"></i>
                        <div>
                            <div>
                                Satu user dapat memiliki lebih dari satu posisi, namun hanya satu posisi yang aktif dalam
                                satu waktu.
                            </div>
                            <span class="d-block mt-2" id="posHistoricalAccessHelper"></span>
                        </div>
                    </div>

                    <div class="users-active-position-panel users-active-position-panel--loading" id="activePositionPanel">
                        <div class="users-active-position-top">
                            <div class="users-active-position-title-wrap">
                                <span class="users-active-position-icon">
                                    <i class="fa fa-circle-notch fa-spin"></i>
                                </span>
                                <div>
                                    <div class="users-active-position-eyebrow">Posisi Aktif Saat Ini</div>
                                    <h6 class="users-active-position-title">Memuat posisi aktif...</h6>
                                    <p class="users-active-position-subtitle">
                                        Ringkasan posisi aktif akan tampil setelah data posisi selesai dimuat.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    @if (($userManagementContext['is_full_admin'] ?? false) === true)
                        <div class="users-position-access-panel">
                            <div class="row g-3 align-items-end">
                                <div class="col-lg-4 col-md-6">
                                    <label class="form-label">Tahun Akses Edit</label>
                                    <div class="users-year-stepper" role="group" aria-label="Pilih tahun akses edit">
                                        <button type="button" class="users-year-stepper__btn"
                                            id="btnAccessYearPrev" title="Tahun sebelumnya"
                                            aria-label="Tahun sebelumnya">
                                            <i class="fa fa-chevron-left"></i>
                                        </button>
                                        <input type="number" class="form-control users-year-stepper__input"
                                            id="posAccessYear" min="2000" max="{{ (int) now()->year }}"
                                            value="{{ min((int) session('tahun_aktif', now()->year), (int) now()->year) }}"
                                            inputmode="numeric" aria-label="Tahun akses edit">
                                        <button type="button" class="users-year-stepper__btn"
                                            id="btnAccessYearNext" title="Tahun berikutnya"
                                            aria-label="Tahun berikutnya">
                                            <i class="fa fa-chevron-right"></i>
                                        </button>
                                    </div>
                                    <div class="users-year-stepper__caption">
                                        <i class="fa fa-clock-rotate-left"></i>
                                        <span>Mundur atau maju satu tahun untuk mengecek izin posisi.</span>
                                    </div>
                                </div>
                                <div class="col-lg-8 col-md-6">
                                    <div class="users-helper mb-0">
                                        Admin Super dapat membuka izin tulis posisi untuk tahun historis melalui daftar
                                        posisi user.
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    <div class="users-position-layout">
                        <div class="users-section-card">
                            <div class="users-section-card__header">
                                <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
                                    <div>
                                        <h6 class="users-section-card__title">Daftar Posisi User</h6>
                                        <p class="users-section-card__desc">Lihat riwayat posisi secara ringkas dan
                                            gunakan menu aksi pada setiap baris untuk edit, aktifkan, nonaktifkan, atau
                                            hapus posisi.</p>
                                    </div>
                                    <button class="btn btn-sm btn-success mb-0" type="button" id="btnOpenPositionForm">
                                        <i class="fa fa-plus me-1"></i>Tambah Posisi
                                    </button>
                                </div>
                            </div>
                            <div class="users-section-card__body">
                                <div id="positionCards" class="users-position-list"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button class="btn btn-light border" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalPositionForm" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header users-modal-header">
                    <div>
                        <h5 class="modal-title users-modal-title" id="positionFormModalTitle">Tambah Posisi</h5>
                        <p class="users-modal-subtitle mb-0" id="positionFormModalSubtitle">
                            Lengkapi data jabatan, scope kerja, masa berlaku, dan dokumen SK posisi user.
                        </p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="positionFormModalBody">
                    <form id="formAddPos" class="users-section-card users-position-form-card"
                        enctype="multipart/form-data">
                        @csrf
                        <div class="users-section-card__header">
                            <div class="users-section-card__heading">
                                <span class="users-section-card__icon">
                                    <i class="fa fa-briefcase"></i>
                                </span>
                                <div>
                                    <h6 class="users-section-card__title" id="posFormTitle">Tambah Posisi</h6>
                                    <p class="users-section-card__desc" id="posFormSubtitle">Lengkapi detail
                                        posisi baru sebelum disimpan ke user ini.</p>
                                </div>
                            </div>
                        </div>
                        <div class="users-section-card__body">
                            <input type="hidden" id="pos_form_mode" value="create">
                            <div class="users-position-form-stack users-position-form-stack--compact">
                                <div class="users-position-form-group">
                                    <div class="users-position-form-group__header">
                                        <span class="users-position-form-group__icon">
                                            <i class="fa fa-sitemap"></i>
                                        </span>
                                        <div>
                                            <h6 class="users-position-form-group__title">Konteks Jabatan</h6>
                                            <p class="users-position-form-group__desc">Jabatan, instansi, dan unit
                                                kerja yang menjadi scope posisi.</p>
                                        </div>
                                    </div>
                                    <div class="row g-2">
                                        <div class="col-lg-4 col-md-6">
                                            <label class="form-label">Jabatan</label>
                                            <select name="jabatan_id" id="pos_jabatan" class="form-select select2-basic"
                                                required data-placeholder="Pilih jabatan">
                                                <option value=""></option>
                                                @foreach ($jabatans as $j)
                                                    <option value="{{ \App\Support\EncryptedId::encode($j->id) }}">
                                                        {{ $j->nama }}</option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div class="col-lg-4 col-md-6">
                                            <label class="form-label">Instansi</label>
                                            <select name="instansi_id" id="pos_instansi"
                                                class="form-select select2-basic" required
                                                data-placeholder="Pilih instansi">
                                                <option value=""></option>
                                            </select>
                                            <small class="text-muted d-none" id="pos_instansi_hint">Instansi wajib
                                                dipilih untuk posisi nyata.</small>
                                        </div>

                                        <div class="col-lg-4 col-md-12">
                                            <label class="form-label">Unit Kerja</label>
                                            <select name="unit_kerja_id" id="pos_unit" class="form-select select2-basic"
                                                required data-placeholder="Pilih unit kerja">
                                                <option value=""></option>
                                            </select>
                                            <small class="text-muted d-none" id="pos_unit_hint">Unit kerja wajib
                                                dipilih untuk posisi nyata.</small>
                                        </div>
                                    </div>
                                </div>

                                <div class="users-position-form-group">
                                    <div class="users-position-form-group__header">
                                        <span class="users-position-form-group__icon">
                                            <i class="fa fa-file-lines"></i>
                                        </span>
                                        <div>
                                            <h6 class="users-position-form-group__title">Dokumen SK</h6>
                                            <p class="users-position-form-group__desc">Metadata dan upload file SK
                                                penetapan posisi.</p>
                                        </div>
                                    </div>
                                    <div class="row g-2">
                                        <div class="col-lg-4 col-md-6">
                                            <label class="form-label">File SK (pdf/jpg/png, max 2MB)</label>
                                            <input type="file" class="form-control" name="file_sk"
                                                accept=".pdf,.jpg,.jpeg,.png">
                                            <small class="text-muted d-none" id="pos_file_helper"></small>
                                        </div>
                                        <div class="col-lg-4 col-md-6">
                                            <label class="form-label">Jenis Dokumen</label>
                                            <select name="document_type" id="pos_document_type"
                                                class="form-select select2-basic" required
                                                data-placeholder="Pilih jenis dokumen">
                                                @foreach (($positionDocumentTypes ?? \App\Models\UserPositionDocument::typeOptions()) as $value => $label)
                                                    <option value="{{ $value }}"
                                                        @selected($value === \App\Models\UserPositionDocument::TYPE_APPOINTMENT_SK)>
                                                        {{ $label }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-lg-4 col-md-6">
                                            <label class="form-label">Nomor SK</label>
                                            <input type="text" class="form-control" name="document_number"
                                                maxlength="150" placeholder="Nomor surat keputusan">
                                        </div>
                                        <div class="col-lg-3 col-md-6">
                                            <label class="form-label">Tanggal SK</label>
                                            <input type="date" class="form-control" name="document_date">
                                        </div>
                                        <div class="col-lg-9 col-md-12">
                                            <label class="form-label">Penerbit SK</label>
                                            <input type="text" class="form-control" name="issued_by" maxlength="200"
                                                placeholder="Pejabat atau instansi penerbit">
                                        </div>
                                    </div>
                                </div>

                                <div class="users-position-form-group">
                                    <div class="users-position-form-group__header">
                                        <span class="users-position-form-group__icon">
                                            <i class="fa fa-calendar-days"></i>
                                        </span>
                                        <div>
                                            <h6 class="users-position-form-group__title">Masa Berlaku, Status &
                                                Catatan</h6>
                                            <p class="users-position-form-group__desc">Periode penugasan, posisi
                                                aktif, dan catatan administratif.</p>
                                        </div>
                                    </div>
                                    <div class="row g-2 align-items-stretch">
                                        <div class="col-lg-3 col-md-6">
                                            <label class="form-label">Mulai</label>
                                            <input type="date" class="form-control" name="started_at" required>
                                        </div>
                                        <div class="col-lg-3 col-md-6">
                                            <label class="form-label">Selesai</label>
                                            <input type="date" class="form-control" name="ended_at">
                                        </div>
                                        <div class="col-lg-6 col-md-12">
                                            <label class="form-label">Status Posisi</label>
                                            <div class="users-position-active-box users-position-active-box--compact">
                                                <input class="form-check-input mt-0" type="checkbox" value="1"
                                                    id="posActive" name="is_active">
                                                <label class="form-check-label" for="posActive">Jadikan posisi
                                                    aktif setelah disimpan</label>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label">Catatan Posisi</label>
                                            <textarea class="form-control" name="notes" rows="2" maxlength="1000"
                                                placeholder="Catatan administratif posisi, penugasan sementara, atau keterangan migrasi data."></textarea>
                                            <div class="users-position-compact-helper">
                                                Opsional. Catatan ini melekat pada posisi user, bukan pada akun
                                                utama.
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="users-position-form-actions">
                                    <button class="btn btn-success mb-0" type="submit" id="btnSubmitPosForm">
                                        <i class="fa fa-plus"></i><span id="btnSubmitPosLabel">Tambah Posisi</span>
                                    </button>
                                    <button class="btn btn-light border d-none mb-0" type="button" id="btnCancelEditPos">
                                        <i class="fa fa-rotate-left"></i>Batal Edit
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-light border" data-bs-dismiss="modal" type="button">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalDelete" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" id="formDeleteUser">
                @csrf
                <div class="modal-header users-modal-header">
                    <div>
                        <h5 class="modal-title users-modal-title">Hapus User</h5>
                        <p class="users-modal-subtitle">Pastikan user sudah tidak dipakai sebelum dihapus dari
                            sistem.</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="users-danger-note">
                        <div class="fw-semibold mb-1">Anda akan menghapus user <span id="delUserName"></span>.</div>
                        <div>Tindakan ini tidak dapat dibatalkan. Pastikan posisi atau kebutuhan aksesnya memang sudah
                            tidak digunakan lagi.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-light border" data-bs-dismiss="modal" type="button">Batal</button>
                    <button class="btn btn-danger" type="submit">
                        <i class="fa fa-trash me-1"></i>Hapus
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="modalUserSecurity" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header users-modal-header">
                    <div>
                        <h5 class="modal-title users-modal-title">Keamanan Akun</h5>
                        <p class="users-modal-subtitle mb-0">
                            <span id="securityUserName" class="fw-semibold text-dark"></span>
                            <span class="text-muted" id="securityUserNik"></span>
                        </p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="users-security-overview mb-3">
                        <div class="users-security-overview__content">
                            <span class="users-security-overview__icon">
                                <i class="fa fa-shield-halved"></i>
                            </span>
                            <div>
                                <div class="users-security-overview__title">Kontrol keamanan administratif</div>
                                <p class="users-security-overview__desc">
                                    Kelola kebijakan password, status lock akun, session, dan MFA tanpa mengubah data
                                    profil user.
                                </p>
                            </div>
                        </div>
                        <span class="users-security-overview__meta">
                            <i class="fa fa-clock-rotate-left"></i>
                            Tercatat di audit trail
                        </span>
                    </div>

                    <div class="users-security-signal-grid mb-3">
                        <div class="users-security-signal users-security-signal--muted" id="securityAccountSignal">
                            <span class="users-security-signal__icon">
                                <i class="fa fa-circle-notch"></i>
                            </span>
                            <div class="users-security-signal__text">
                                <div class="users-security-signal__label">Akses Akun</div>
                                <div class="users-security-signal__value" id="securityAccountSignalValue">Memuat...</div>
                            </div>
                        </div>
                        <div class="users-security-signal users-security-signal--muted" id="securityPasswordSignal">
                            <span class="users-security-signal__icon">
                                <i class="fa fa-circle-notch"></i>
                            </span>
                            <div class="users-security-signal__text">
                                <div class="users-security-signal__label">Password</div>
                                <div class="users-security-signal__value" id="securityPasswordSignalValue">Memuat...</div>
                            </div>
                        </div>
                        <div class="users-security-signal users-security-signal--muted" id="securityMfaSignal">
                            <span class="users-security-signal__icon">
                                <i class="fa fa-circle-notch"></i>
                            </span>
                            <div class="users-security-signal__text">
                                <div class="users-security-signal__label">MFA</div>
                                <div class="users-security-signal__value" id="securityMfaSignalValue">Memuat...</div>
                            </div>
                        </div>
                    </div>

                    <div class="users-section-card mb-3">
                        <div class="users-section-card__header">
                            <h6 class="users-section-card__title">Status Akun</h6>
                            <p class="users-section-card__desc">Ringkasan status operasional dan kunci akun.</p>
                        </div>
                        <div class="users-section-card__body">
                            <div class="users-security-grid">
                                <div class="users-position-field">
                                    <span class="users-position-field__label">Status</span>
                                    <div class="users-position-field__value">
                                        <span class="badge" id="securityStatusBadge">-</span>
                                    </div>
                                    <div class="users-position-field__helper" id="securityStatusChanged">-</div>
                                </div>
                                <div class="users-position-field">
                                    <span class="users-position-field__label">Alasan Status</span>
                                    <div class="users-position-field__value" id="securityStatusReason">-</div>
                                </div>
                                <div class="users-position-field">
                                    <span class="users-position-field__label">Dikunci Pada</span>
                                    <div class="users-position-field__value" id="securityLockedAt">-</div>
                                </div>
                                <div class="users-position-field">
                                    <span class="users-position-field__label">Dikunci Sampai</span>
                                    <div class="users-position-field__value" id="securityLockedUntil">-</div>
                                    <div class="users-position-field__helper" id="securityLockReason">-</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="users-section-card mb-3">
                        <div class="users-section-card__header">
                            <h6 class="users-section-card__title">Password & MFA</h6>
                            <p class="users-section-card__desc">Status kewajiban ganti password dan konfigurasi MFA.</p>
                        </div>
                        <div class="users-section-card__body">
                            <div class="users-security-grid">
                                <div class="users-position-field">
                                    <span class="users-position-field__label">Password</span>
                                    <div class="users-position-field__value" id="securityPasswordState">-</div>
                                    <div class="users-position-field__helper" id="securityPasswordChanged">-</div>
                                </div>
                                <div class="users-position-field">
                                    <span class="users-position-field__label">Reset Password</span>
                                    <div class="users-position-field__value" id="securityPasswordResetAt">-</div>
                                    <div class="users-position-field__helper" id="securityPasswordResetBy">-</div>
                                </div>
                                <div class="users-position-field">
                                    <span class="users-position-field__label">MFA</span>
                                    <div class="users-position-field__value">
                                        <span class="badge" id="securityMfaBadge">-</span>
                                    </div>
                                    <div class="users-position-field__helper" id="securityMfaEnabled">-</div>
                                </div>
                                <div class="users-position-field">
                                    <span class="users-position-field__label">Recovery Code</span>
                                    <div class="users-position-field__value" id="securityMfaRecoveryCount">-</div>
                                    <div class="users-position-field__helper" id="securityMfaLastUsed">-</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="users-section-card">
                        <div class="users-section-card__header">
                            <h6 class="users-section-card__title">Aktivitas & Aksi</h6>
                            <p class="users-section-card__desc">Aktivitas login terakhir dan aksi keamanan administratif.</p>
                        </div>
                        <div class="users-section-card__body">
                            <div class="users-security-grid mb-3">
                                <div class="users-position-field">
                                    <span class="users-position-field__label">Login Terakhir</span>
                                    <div class="users-position-field__value" id="securityLastLogin">-</div>
                                </div>
                                <div class="users-position-field">
                                    <span class="users-position-field__label">Gagal Login</span>
                                    <div class="users-position-field__value" id="securityFailedLogin">-</div>
                                    <div class="users-position-field__helper" id="securitySessionInvalidated">-</div>
                                </div>
                            </div>
                            <div class="users-security-action-groups">
                                <div class="users-security-action-group">
                                    <div class="users-security-action-group__header">
                                        <i class="fa fa-key"></i>
                                        <span>Password</span>
                                    </div>
                                    <div class="users-security-actions">
                                        <button type="button" class="btn btn-outline-primary users-security-action"
                                            id="btnSecurityForcePassword">
                                            <span class="users-security-action__icon">
                                                <i class="fa fa-key"></i>
                                            </span>
                                            <span class="users-security-action__text">
                                                <span class="users-security-action__title">Force Change Password</span>
                                                <span class="users-security-action__desc">User wajib mengganti password
                                                    saat login berikutnya.</span>
                                            </span>
                                        </button>
                                        <button type="button" class="btn btn-outline-danger users-security-action"
                                            id="btnSecurityResetPassword">
                                            <span class="users-security-action__icon">
                                                <i class="fa fa-rotate-right"></i>
                                            </span>
                                            <span class="users-security-action__text">
                                                <span class="users-security-action__title">Reset Password</span>
                                                <span class="users-security-action__desc">Buat password sementara dan
                                                    cabut session user.</span>
                                            </span>
                                        </button>
                                    </div>
                                </div>
                                <div class="users-security-action-group">
                                    <div class="users-security-action-group__header">
                                        <i class="fa fa-user-lock"></i>
                                        <span>Akses Akun</span>
                                    </div>
                                    <div class="users-security-actions">
                                        <button type="button" class="btn btn-outline-danger users-security-action"
                                            id="btnSecurityLock">
                                            <span class="users-security-action__icon">
                                                <i class="fa fa-lock"></i>
                                            </span>
                                            <span class="users-security-action__text">
                                                <span class="users-security-action__title">Lock Account</span>
                                                <span class="users-security-action__desc">Blokir sementara akses login
                                                    user ini.</span>
                                            </span>
                                        </button>
                                        <button type="button" class="btn btn-outline-success users-security-action"
                                            id="btnSecurityUnlock">
                                            <span class="users-security-action__icon">
                                                <i class="fa fa-lock-open"></i>
                                            </span>
                                            <span class="users-security-action__text">
                                                <span class="users-security-action__title">Unlock Account</span>
                                                <span class="users-security-action__desc">Buka kembali akun yang sedang
                                                    terkunci.</span>
                                            </span>
                                        </button>
                                    </div>
                                </div>
                                <div class="users-security-action-group">
                                    <div class="users-security-action-group__header">
                                        <i class="fa fa-mobile-screen-button"></i>
                                        <span>MFA</span>
                                    </div>
                                    <div class="users-security-actions users-security-actions--single">
                                        <button type="button" class="btn btn-outline-warning users-security-action"
                                            id="btnSecurityResetMfa">
                                            <span class="users-security-action__icon">
                                                <i class="fa fa-mobile-screen-button"></i>
                                            </span>
                                            <span class="users-security-action__text">
                                                <span class="users-security-action__title">Reset MFA</span>
                                                <span class="users-security-action__desc">Hapus konfigurasi MFA agar
                                                    user setup ulang.</span>
                                            </span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-light border" data-bs-dismiss="modal" type="button">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalUserSecurityReason" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" id="formUserSecurityReason">
                @csrf
                <div class="modal-header users-modal-header">
                    <div>
                        <h5 class="modal-title users-modal-title" id="securityReasonTitle">Alasan Keamanan Akun</h5>
                        <p class="users-modal-subtitle mb-0" id="securityReasonSubtitle">Isi alasan administratif.</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="users-security-reason-alert mb-3">
                        <span class="users-security-reason-alert__icon">
                            <i class="fa fa-triangle-exclamation"></i>
                        </span>
                        <div>
                            <div class="users-security-reason-alert__title">Dampak Aksi</div>
                            <div class="users-security-reason-alert__desc" id="securityReasonWarning">
                                Aksi ini akan memperbarui status keamanan akun dan membatalkan sesi aktif user.
                            </div>
                        </div>
                    </div>

                    <div class="users-security-reason-fields">
                        <div class="users-security-reason-field d-none" id="securityLockedUntilWrap">
                            <label class="form-label" for="securityLockedUntilInput">
                                <i class="fa fa-clock text-primary"></i>Dikunci Sampai
                            </label>
                            <input class="form-control" type="datetime-local" name="locked_until"
                                id="securityLockedUntilInput">
                            <div class="users-helper mt-2">
                                Kosongkan jika akun dikunci sampai dibuka manual.
                            </div>
                        </div>

                        <div class="users-security-reason-field">
                            <label class="form-label" for="securityReasonInput">
                                <i class="fa fa-clipboard-list text-primary"></i>Alasan Administratif
                            </label>
                            <textarea class="form-control" name="reason" id="securityReasonInput" rows="5" maxlength="500" required
                                placeholder="Contoh: Permintaan reset akses dari user melalui surat resmi."></textarea>
                            <div class="users-security-reason-audit mt-3">
                                <i class="fa fa-clock-rotate-left"></i>
                                <span>Alasan ini disimpan sebagai jejak audit keamanan akun untuk kebutuhan pemeriksaan,
                                    debugging, dan laporan administrasi.</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-light border" data-bs-dismiss="modal" type="button">Batal</button>
                    <button class="btn btn-primary" type="submit" id="securityReasonSubmit">
                        <i class="fa fa-floppy-disk me-1"></i>Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="modalUserSecurityPasswordResult" tabindex="-1" aria-hidden="true"
        data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header users-modal-header">
                    <div>
                        <h5 class="modal-title users-modal-title">Password Sementara</h5>
                        <p class="users-modal-subtitle mb-0">
                            <span id="securityPasswordResultUserName" class="fw-semibold text-dark"></span>
                        </p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="users-security-reason-alert mb-3">
                        <span class="users-security-reason-alert__icon">
                            <i class="fa fa-eye"></i>
                        </span>
                        <div>
                            <div class="users-security-reason-alert__title">Tampil Satu Kali</div>
                            <div class="users-security-reason-alert__desc">
                                Simpan atau salin password ini sekarang. Setelah modal ditutup, password sementara tidak
                                dapat ditampilkan ulang.
                            </div>
                        </div>
                    </div>

                    <div class="users-temporary-password-card mb-3">
                        <div class="users-temporary-password-card__header">
                            <div class="users-temporary-password-card__title">
                                <i class="fa fa-key"></i>
                                <span>Password Sementara</span>
                            </div>
                            <span class="users-temporary-password-status" id="securityTemporaryPasswordCopyStatus">
                                <i class="fa fa-clock"></i>
                                Belum disalin
                            </span>
                        </div>

                        <div class="users-temporary-password-box">
                            <div class="users-temporary-password-value" id="securityTemporaryPasswordValue">-</div>
                            <button type="button" class="btn btn-primary users-temporary-password-copy"
                                id="btnCopyTemporaryPassword">
                                <i class="fa fa-copy"></i>
                                <span id="btnCopyTemporaryPasswordLabel">Salin</span>
                            </button>
                        </div>

                        <div class="users-temporary-password-checklist">
                            <div class="users-temporary-password-checkitem">
                                <i class="fa fa-circle-check"></i>
                                <span>Password lama tidak berlaku setelah reset berhasil.</span>
                            </div>
                            <div class="users-temporary-password-checkitem">
                                <i class="fa fa-circle-check"></i>
                                <span>Session aktif user dicabut dan user wajib mengganti password setelah login.</span>
                            </div>
                        </div>
                    </div>

                    <div class="users-temporary-password-note">
                        <i class="fa fa-circle-info"></i>
                        <span>User wajib login memakai password sementara ini, lalu mengganti password baru sebelum
                            melanjutkan ke dashboard.</span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-light border" data-bs-dismiss="modal" type="button">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalDeactivatePosition" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" id="formDeactivatePosition">
                @csrf
                <input type="hidden" id="deactivatePositionActionUrl">
                <div class="modal-header users-modal-header">
                    <div>
                        <h5 class="modal-title users-modal-title">Nonaktifkan Posisi</h5>
                        <p class="users-modal-subtitle mb-0">
                            Isi alasan administratif sebelum posisi dinonaktifkan.
                        </p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="users-admin-reason-alert mb-3">
                        <span class="users-admin-reason-alert__icon">
                            <i class="fa fa-user-slash"></i>
                        </span>
                        <div>
                            <div class="users-admin-reason-alert__title" id="deactivatePositionName">Posisi akan
                                dinonaktifkan.</div>
                            <div class="users-admin-reason-alert__desc">
                                Posisi nonaktif tidak bisa dipilih sebagai posisi kerja aktif, tetapi riwayat datanya
                                tetap tersimpan.
                            </div>
                        </div>
                    </div>

                    <div class="users-admin-reason-fields">
                        <div class="users-admin-reason-field">
                            <label class="form-label" for="deactivatePositionEndedAt">
                                <i class="fa fa-calendar-check text-primary"></i>Tanggal Selesai
                            </label>
                            <input class="form-control" type="date" name="ended_at" id="deactivatePositionEndedAt">
                            <div class="users-helper mt-2">
                                Jika dikosongkan, sistem memakai tanggal hari ini.
                            </div>
                        </div>

                        <div class="users-admin-reason-field">
                            <label class="form-label" for="deactivatePositionReason">
                                <i class="fa fa-clipboard-list text-primary"></i>Alasan Nonaktif
                            </label>
                            <textarea class="form-control" name="deactivation_reason" id="deactivatePositionReason" rows="5" maxlength="500"
                                required placeholder="Contoh: Masa penugasan selesai berdasarkan SK baru."></textarea>
                            <div class="users-admin-reason-audit mt-3">
                                <i class="fa fa-clock-rotate-left"></i>
                                <span>Alasan ini disimpan sebagai jejak audit posisi user dan membantu menelusuri
                                    perubahan penugasan administratif.</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-light border" data-bs-dismiss="modal" type="button">Batal</button>
                    <button class="btn btn-warning" type="submit">
                        <i class="fa fa-user-slash me-1"></i>Nonaktifkan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="modalHistoricalReason" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <form class="modal-content users-historical-modal" id="formHistoricalReason">
                @csrf
                <input type="hidden" name="tahun" id="historicalReasonYear">
                <input type="hidden" id="historicalReasonActionUrl">
                <input type="hidden" id="historicalReasonMode">
                <div class="modal-header users-modal-header">
                    <div class="users-historical-heading">
                        <span class="users-historical-icon" id="historicalReasonIcon">
                            <i class="fa fa-user-shield" id="historicalReasonIconSymbol"></i>
                        </span>
                        <div>
                            <h5 class="modal-title users-modal-title" id="historicalReasonTitle">Alasan Izin Historis</h5>
                            <p class="users-modal-subtitle mb-0" id="historicalReasonSubtitle">
                                Isi alasan administratif sebelum melanjutkan.
                            </p>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="users-historical-context">
                        <div class="users-historical-context__item">
                            <div class="users-historical-context__label">
                                <i class="fa fa-calendar-days"></i>Tahun Target
                            </div>
                            <div class="users-historical-context__value" id="historicalReasonYearLabel">-</div>
                        </div>
                        <div class="users-historical-context__item">
                            <div class="users-historical-context__label">
                                <i class="fa fa-shield-halved"></i>Jenis Aksi
                            </div>
                            <div class="users-historical-context__value" id="historicalReasonActionLabel">-</div>
                        </div>
                        <div class="users-historical-context__item">
                            <div class="users-historical-context__label">
                                <i class="fa fa-lock-open"></i>Dampak
                            </div>
                            <div class="users-historical-context__value" id="historicalReasonImpactLabel">-</div>
                        </div>
                    </div>

                    <div class="users-historical-card">
                        <div class="users-historical-card__header">
                            <div>
                                <h6 class="users-historical-card__title">
                                    <i class="fa fa-clipboard-list"></i>Alasan Administratif
                                </h6>
                                <p class="users-historical-card__desc">
                                    Jelaskan dasar perubahan akses agar keputusan mudah ditelusuri kembali.
                                </p>
                            </div>
                            <span class="users-historical-required">
                                <i class="fa fa-circle-exclamation"></i>Wajib
                            </span>
                        </div>

                        <textarea class="form-control" name="reason" id="historicalReasonInput" rows="4" maxlength="500"
                            required placeholder="Contoh: Perbaikan data tahun historis berdasarkan hasil review."></textarea>

                        <div class="users-historical-audit-note">
                            <i class="fa fa-clock-rotate-left"></i>
                            <span>Alasan ini disimpan sebagai jejak audit grant/revoke izin tulis tahun historis.</span>
                        </div>
                    </div>

                    <div class="users-historical-card d-none" id="historicalGrantMetadata">
                        <div class="users-historical-card__header">
                            <div>
                                <h6 class="users-historical-card__title">
                                    <i class="fa fa-file-signature"></i>Referensi Grant
                                </h6>
                                <p class="users-historical-card__desc">
                                    Lengkapi metadata bila izin historis diberikan berdasarkan dokumen, memo, tiket,
                                    atau persetujuan administratif.
                                </p>
                            </div>
                        </div>

                        <div class="users-historical-metadata-grid">
                            <div>
                                <label class="form-label" for="historicalReferenceNumber">Nomor Referensi</label>
                                <input class="form-control" type="text" name="reference_number"
                                    id="historicalReferenceNumber" maxlength="150"
                                    placeholder="Nomor memo, SK, atau tiket">
                            </div>
                            <div>
                                <label class="form-label" for="historicalReferenceDate">Tanggal Referensi</label>
                                <input class="form-control" type="date" name="reference_date"
                                    id="historicalReferenceDate">
                            </div>
                            <div>
                                <label class="form-label" for="historicalValidUntil">Berlaku Sampai</label>
                                <input class="form-control" type="date" name="valid_until" id="historicalValidUntil">
                                <div class="users-position-compact-helper">
                                    Kosongkan jika izin berlaku sampai dicabut manual.
                                </div>
                            </div>
                            <div class="users-historical-field--wide">
                                <label class="form-label" for="historicalGrantNotes">Catatan Grant</label>
                                <textarea class="form-control" name="grant_notes" id="historicalGrantNotes" rows="3"
                                    maxlength="1000" placeholder="Catatan tambahan khusus pemberian izin."></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-light border" data-bs-dismiss="modal" type="button">Batal</button>
                    <button class="btn btn-primary" type="submit" id="historicalReasonSubmit">
                        <i class="fa fa-floppy-disk me-1"></i>Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection

@section('additionals')
    <script>
        window.userManagementContext = @json($userManagementContext ?? []);
        const modalUser = new bootstrap.Modal('#modalUser');
        const modalPositions = new bootstrap.Modal('#modalPositions');
        const modalPositionForm = new bootstrap.Modal('#modalPositionForm');
        const modalDelete = new bootstrap.Modal('#modalDelete');
        const modalUserSecurity = new bootstrap.Modal('#modalUserSecurity');
        const modalUserSecurityReason = new bootstrap.Modal('#modalUserSecurityReason');
        const modalUserSecurityPasswordResult = new bootstrap.Modal('#modalUserSecurityPasswordResult');
        const modalDeactivatePosition = new bootstrap.Modal('#modalDeactivatePosition');
        const modalHistoricalReason = new bootstrap.Modal('#modalHistoricalReason');
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });

        $(function() {
            let latestMetrics = null;
            const managedAccessMinYear = 2000;
            const managedAccessCurrentYear = Number(window.yearAccessContext?.tahun_sekarang ||
                @json((int) now()->year));
            const managedAccessMaxYear = managedAccessCurrentYear;
            const initialManagedAccessYear = Number(window.yearAccessContext?.tahun_aktif ||
                @json((int) session('tahun_aktif', now()->year)));
            const defaultManagedAccessYear = Math.min(
                Math.max(initialManagedAccessYear, managedAccessMinYear),
                managedAccessMaxYear
            );
            const isFullAdminUserManagement = !!window.userManagementContext?.is_full_admin;
            const defaultPositionDocumentType = @json(\App\Models\UserPositionDocument::TYPE_APPOINTMENT_SK);
            const userAccountTypeLabels = @json($userAccountTypes ?? []);
            const userAccountStatusLabels = @json($userAccountStatuses ?? []);

            const formatNumber = (value) => new Intl.NumberFormat('id-ID').format(Number(value || 0));
            const escapeHtml = (value) => $('<div>').text(value ?? '').html();
            const localDateInputValue = (date = new Date()) => {
                const localDate = new Date(date);
                localDate.setMinutes(localDate.getMinutes() - localDate.getTimezoneOffset());

                return localDate.toISOString().slice(0, 10);
            };
            const localDateTimeInputValue = (date = new Date()) => {
                const localDate = new Date(date);
                localDate.setMinutes(localDate.getMinutes() - localDate.getTimezoneOffset());

                return localDate.toISOString().slice(0, 16);
            };
            const blank = (value, fallback = '-') => {
                const normalized = String(value ?? '').trim();

                return normalized === '' ? fallback : normalized;
            };
            const isCurrentManagedAccessYear = (year) => Number(year) === managedAccessCurrentYear;
            const ajaxErrorMessage = (xhr, fallback) => {
                if (xhr.responseJSON?.errors) {
                    const firstFieldErrors = Object.values(xhr.responseJSON.errors)[0];
                    if (Array.isArray(firstFieldErrors) && firstFieldErrors.length) {
                        return firstFieldErrors[0];
                    }
                }

                return xhr.responseJSON?.message || fallback;
            };
            const userFilterPayload = () => ({
                status: $('#filter_status').val() || '',
                account_type: $('#filter_account_type').val() || '',
                jabatan_id: $('#filter_jabatan_id').val() || '',
                instansi_id: $('#filter_instansi_id').val() || '',
                unit_kerja_id: $('#filter_unit_kerja_id').val() || '',
                position_state: $('#filter_position_state').val() || ''
            });
            const lockedUserStatus = @json(\App\Models\User::STATUS_LOCKED);
            const lockedUserStatusLabel = @json($userAccountStatuses[\App\Models\User::STATUS_LOCKED] ?? 'Terkunci');

            const dt = $('#users-table').DataTable({
                processing: true,
                serverSide: true,
                responsive: true,
                searching: true,
                lengthChange: true,
                pageLength: 10,
                ajax: {
                    url: @json(route('users.datatable')),
                    type: 'GET',
                    data: function(data) {
                        Object.assign(data, userFilterPayload());
                    }
                },
                language: {
                    processing: 'Memuat data user...',
                    zeroRecords: 'Tidak ada user yang sesuai dengan pencarian.',
                    info: 'Menampilkan _START_ sampai _END_ dari _TOTAL_ user',
                    infoEmpty: 'Belum ada data user',
                    lengthMenu: '_MENU_ user per halaman',

                },
                columns: [{
                        data: 'index',
                        className: 'dt-center',
                        searchable: false,
                        orderable: false
                    },
                    {
                        data: 'nik'
                    },
                    {
                        data: 'nama'
                    },
                    {
                        data: 'email'
                    },
                    {
                        data: 'account_status',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'security_status',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'used_position',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'positions_count',
                        className: 'dt-center',
                        orderable: true,
                        searchable: false
                    },
                    {
                        data: 'actions',
                        className: 'dt-center',
                        orderable: false,
                        searchable: false
                    }
                ],
                order: [
                    [2, 'asc']
                ]
            });

            function updateDashboardMetrics() {
                const info = dt.page.info();
                const rows = dt.rows({
                    page: 'current'
                }).data().toArray();
                const totalPositions = rows.reduce((sum, row) => sum + Number(row.positions_total_raw || 0), 0);
                const search = (dt.search() || '').trim();
                const hasStructuredFilter = Object.values(userFilterPayload()).some((value) => String(value || '')
                    .trim() !== '');

                $('#metricTotalUsers').text(formatNumber(latestMetrics?.recordsTotal || info.recordsTotal || 0));
                $('#metricFilteredUsers').text(formatNumber(latestMetrics?.recordsFiltered || info.recordsDisplay || 0));
                $('#metricPagePositions').text(formatNumber(totalPositions));
                $('#metricSearchState').text(search || hasStructuredFilter ? 'Filter Aktif' : 'Semua User');
                $('#metricFilteredHelper').text(search ? `Hasil untuk kata kunci "${search}".` : hasStructuredFilter ?
                    'Menampilkan user sesuai filter yang dipilih.' :
                    'Menampilkan seluruh user tanpa filter.');
                $('#metricRangeHelper').text(info.recordsDisplay ? `Baris ${info.start + 1}-${info.end} dari ${
                    formatNumber(info.recordsDisplay)
                } user.` : 'Belum ada data untuk ditampilkan.');
            }

            dt.on('xhr.dt', function(e, settings, json) {
                latestMetrics = json || null;
            });

            dt.on('draw.dt', function() {
                updateDashboardMetrics();
            });

            $('#users-table_filter input').attr('placeholder', 'Cari nama, NIK, NIP, atau email');

            function initSelect2(parent) {
                $(parent).find('.select2-basic').each(function() {
                    const $select = $(this);

                    if ($select.hasClass('select2-hidden-accessible')) {
                        return;
                    }

                    $select.select2({
                        theme: 'bootstrap-5',
                        dropdownParent: $(parent),
                        width: '100%',
                        placeholder: $select.data('placeholder') || '',
                        allowClear: true
                    });
                });
            }

            const userFilterDefinitions = [{
                    selector: '#filter_status',
                    label: 'Status'
                },
                {
                    selector: '#filter_account_type',
                    label: 'Tipe'
                },
                {
                    selector: '#filter_position_state',
                    label: 'Posisi'
                },
                {
                    selector: '#filter_jabatan_id',
                    label: 'Jabatan'
                },
                {
                    selector: '#filter_instansi_id',
                    label: 'Instansi'
                },
                {
                    selector: '#filter_unit_kerja_id',
                    label: 'Unit'
                }
            ];

            function selectedFilterText(selector) {
                const $select = $(selector);
                const value = $select.val();

                if (!value) {
                    return '';
                }

                const select2Data = $select.hasClass('select2-hidden-accessible') ? $select.select2('data') : [];
                const text = select2Data?.[0]?.text || $select.find('option:selected').text();

                return String(text || '').trim();
            }

            function activeUserFilters() {
                return userFilterDefinitions
                    .map((filter) => ({
                        ...filter,
                        value: $(filter.selector).val(),
                        text: selectedFilterText(filter.selector)
                    }))
                    .filter((filter) => !!filter.value && filter.text !== '');
            }

            function updateUsersFilterState() {
                const activeFilters = activeUserFilters();
                const $chips = $('#usersActiveFilterChips');

                $('#usersFilterSummary').text(activeFilters.length ? `${activeFilters.length} filter aktif` :
                    'Semua user');
                $('#btnResetUserFilters').toggleClass('d-none', activeFilters.length === 0);
                $chips.toggleClass('d-none', activeFilters.length === 0).empty();

                activeFilters.forEach((filter) => {
                    $chips.append(`
                        <span class="users-active-filter-chip">
                            <span>${escapeHtml(filter.label)}: ${escapeHtml(filter.text)}</span>
                            <button type="button" class="btnRemoveUserFilter" data-filter-target="${filter.selector}" aria-label="Hapus filter ${escapeHtml(filter.label)}">
                                <i class="fa fa-xmark"></i>
                            </button>
                        </span>
                    `);
                });
            }

            function resetFilterSelect($select, disabled = true) {
                $select.empty().append(new Option('', '', true, true));
                $select.prop('disabled', disabled).val('').trigger('change.select2');
            }

            function appendFilterOptions($select, options) {
                (options || []).forEach((option) => {
                    $select.append(new Option(option.text, option.id, false, false));
                });
            }

            function loadFilterInstansiOptions(jabatanId) {
                const $instansi = $('#filter_instansi_id');
                const $unit = $('#filter_unit_kerja_id');

                resetFilterSelect($instansi);
                resetFilterSelect($unit);

                if (!jabatanId) {
                    return $.Deferred().resolve().promise();
                }

                return $.get(@json(route('ajax.options.instansi')), {
                        jabatan_id: jabatanId,
                        scope: 'users-management'
                    })
                    .done(function(response) {
                        const options = response.options || [];

                        appendFilterOptions($instansi, options);
                        $instansi.prop('disabled', options.length === 0).trigger('change.select2');
                    });
            }

            function loadFilterUnitOptions(instansiId, jabatanId) {
                const $unit = $('#filter_unit_kerja_id');

                resetFilterSelect($unit);

                if (!instansiId || !jabatanId) {
                    return $.Deferred().resolve().promise();
                }

                return $.get(@json(route('ajax.options.unitkerja')), {
                        instansi_id: instansiId,
                        jabatan_id: jabatanId,
                        scope: 'users-management'
                    })
                    .done(function(response) {
                        const options = response.options || [];

                        appendFilterOptions($unit, options);
                        $unit.prop('disabled', options.length === 0).trigger('change.select2');
                    });
            }

            function reloadUsersTable() {
                dt.ajax.reload(null, true);
            }

            initSelect2('#usersFilterPanel');
            updateUsersFilterState();

            $('#filter_status, #filter_account_type, #filter_position_state, #filter_unit_kerja_id').on('change',
                function() {
                    updateUsersFilterState();
                    reloadUsersTable();
                });

            $('#filter_jabatan_id').on('change', function() {
                const jabatanId = $(this).val();

                loadFilterInstansiOptions(jabatanId)
                    .fail(function() {
                        Swal.fire('Error', 'Gagal memuat filter instansi.', 'error');
                    });
                updateUsersFilterState();
                reloadUsersTable();
            });

            $('#filter_instansi_id').on('change', function() {
                const instansiId = $(this).val();
                const jabatanId = $('#filter_jabatan_id').val();

                loadFilterUnitOptions(instansiId, jabatanId)
                    .fail(function() {
                        Swal.fire('Error', 'Gagal memuat filter unit kerja.', 'error');
                    });
                updateUsersFilterState();
                reloadUsersTable();
            });

            $('#btnApplyUserFilters').on('click', function() {
                updateUsersFilterState();
                reloadUsersTable();
            });

            $('#btnResetUserFilters').on('click', function() {
                $('#filter_status, #filter_account_type, #filter_position_state, #filter_jabatan_id').val('')
                    .trigger('change.select2');
                resetFilterSelect($('#filter_instansi_id'));
                resetFilterSelect($('#filter_unit_kerja_id'));
                updateUsersFilterState();
                reloadUsersTable();
            });

            $('#usersActiveFilterChips').on('click', '.btnRemoveUserFilter', function() {
                const target = $(this).data('filter-target');

                if (target === '#filter_jabatan_id') {
                    $('#filter_jabatan_id').val('').trigger('change.select2');
                    resetFilterSelect($('#filter_instansi_id'));
                    resetFilterSelect($('#filter_unit_kerja_id'));
                } else if (target === '#filter_instansi_id') {
                    $('#filter_instansi_id').val('').trigger('change.select2');
                    resetFilterSelect($('#filter_unit_kerja_id'));
                } else {
                    $(target).val('').trigger('change.select2');
                }

                updateUsersFilterState();
                reloadUsersTable();
            });

            function setUserModalMode(mode) {
                const $oldPasswordWrap = $('#oldPasswordWrap');
                const $oldPasswordInput = $oldPasswordWrap.find('input[name=old_password]');

                if (mode === 'edit') {
                    $('#titleUser').text('Edit User');
                    $('#subtitleUser').text(
                        'Perbarui profil akun dan preferensi dasar user.');
                    $('#hintPass').text('(kosongkan bila tidak ingin mengganti)');
                    $oldPasswordWrap.removeClass('d-none');
                } else {
                    $('#titleUser').text('Tambah User');
                    $('#subtitleUser').text(
                        'Buat profil akun terlebih dahulu, lalu lanjutkan pengaturan posisi user.'
                    );
                    $('#hintPass').text('');
                    $oldPasswordWrap.addClass('d-none');
                    $oldPasswordInput.val('');
                }
            }

            function syncUserStatusReasonRequirement() {
                const requiresReason = $('#user_status').val() !== @json(\App\Models\User::STATUS_ACTIVE);
                $('#user_status_reason').prop('required', requiresReason);
                $('#userStatusReasonHint').toggleClass('d-none', !requiresReason);
                $('#userLockedStatusHint').toggleClass('d-none', $('#user_status').val() !== lockedUserStatus);
            }

            function resetTemporaryLockedStatusOption() {
                $('#user_status option[data-temporary-status="locked"]').remove();
            }

            function syncTemporaryLockedStatusOption(status) {
                resetTemporaryLockedStatusOption();

                if (status !== lockedUserStatus) {
                    return;
                }

                const $status = $('#user_status');

                if ($status.find(`option[value="${lockedUserStatus}"]`).length) {
                    return;
                }

                const option = new Option(`${lockedUserStatusLabel} - kelola via Keamanan Akun`, lockedUserStatus);
                $(option).attr('data-temporary-status', 'locked');
                $status.append(option);
            }

            function loadPositionInstansiOptions(jabatanId, selectedInstansi = null) {
                const $instansi = $('#pos_instansi');
                const $unit = $('#pos_unit');
                const selectedInstansiId = typeof selectedInstansi === 'object' ? selectedInstansi?.id : selectedInstansi;
                const selectedInstansiText = typeof selectedInstansi === 'object' ? selectedInstansi?.text : null;

                $instansi.empty().append('<option value=""></option>').trigger('change').prop('disabled', true);
                $unit.empty().append('<option value=""></option>').trigger('change').prop('disabled', true);

                if (!jabatanId) {
                    $('#pos_instansi_hint, #pos_unit_hint').addClass('d-none');
                    $('#pos_instansi, #pos_unit').attr('required', true);
                    return $.Deferred().resolve().promise();
                }

                return $.get(@json(route('ajax.options.instansi')), {
                        jabatan_id: jabatanId,
                        scope: 'users-management'
                    })
                    .done(function(resp) {
                        const opts = resp.options || [];

                        $('#pos_instansi_hint, #pos_unit_hint').addClass('d-none');
                        $('#pos_instansi, #pos_unit').attr('required', true);

                        opts.forEach(function(o) {
                            $instansi.append(new Option(o.text, o.id));
                        });

                        if (selectedInstansiId) {
                            if (!$instansi.find(`option[value="${selectedInstansiId}"]`).length && selectedInstansiText) {
                                $instansi.append(new Option(selectedInstansiText, selectedInstansiId));
                            }
                            $instansi.val(selectedInstansiId);
                        } else if (opts.length === 1) {
                            $instansi.val(opts[0].id);
                        }

                        $instansi.prop('disabled', false).trigger(selectedInstansiId ? 'change.select2' : 'change');
                    });
            }

            function loadPositionUnitOptions(instansiId, jabatanId, selectedUnit = null) {
                const $unit = $('#pos_unit');
                const selectedUnitId = typeof selectedUnit === 'object' ? selectedUnit?.id : selectedUnit;
                const selectedUnitText = typeof selectedUnit === 'object' ? selectedUnit?.text : null;

                $unit.empty().append('<option value=""></option>').trigger('change');

                if (!instansiId || !jabatanId) {
                    $unit.prop('disabled', true);
                    return $.Deferred().resolve().promise();
                }

                return $.get(@json(route('ajax.options.unitkerja')), {
                        instansi_id: instansiId,
                        jabatan_id: jabatanId,
                        scope: 'users-management'
                    })
                    .done(function(resp) {
                        (resp.options || []).forEach(function(o) {
                            $unit.append(new Option(o.text, o.id));
                        });

                        if (selectedUnitId) {
                            if (!$unit.find(`option[value="${selectedUnitId}"]`).length && selectedUnitText) {
                                $unit.append(new Option(selectedUnitText, selectedUnitId));
                            }
                            $unit.val(selectedUnitId);
                        } else if ((resp.options || []).length === 1) {
                            $unit.val(resp.options[0].id);
                        }

                        $unit.prop('disabled', false).trigger('change');
                    });
            }

            function normalizeManagedAccessYear(year = null) {
                const normalizedYear = Number(year || defaultManagedAccessYear);

                if (!Number.isFinite(normalizedYear)) {
                    return defaultManagedAccessYear;
                }

                return Math.min(
                    Math.max(Math.trunc(normalizedYear), managedAccessMinYear),
                    managedAccessMaxYear
                );
            }

            function updateManagedAccessYearStepper(year = null) {
                if (!isFullAdminUserManagement || !$('#posAccessYear').length) {
                    return;
                }

                const safeYear = normalizeManagedAccessYear(year || $('#posAccessYear').val());

                $('#btnAccessYearPrev')
                    .prop('disabled', safeYear <= managedAccessMinYear)
                    .attr('aria-disabled', safeYear <= managedAccessMinYear ? 'true' : 'false');
                $('#btnAccessYearNext')
                    .prop('disabled', safeYear >= managedAccessMaxYear)
                    .attr('aria-disabled', safeYear >= managedAccessMaxYear ? 'true' : 'false');
                $('#posAccessYear')
                    .attr('min', managedAccessMinYear)
                    .attr('max', managedAccessMaxYear)
                    .attr('aria-valuenow', safeYear);
            }

            function applyManagedAccessYear(year = null) {
                syncManagedAccessYear(year);
                updateHistoricalAccessSummary();

                if (currentUser) {
                    resetPositionFormMode();
                    loadPositionsTable();
                }
            }

            function getManagedAccessYear() {
                if (!isFullAdminUserManagement || !$('#posAccessYear').length) {
                    return defaultManagedAccessYear;
                }

                return normalizeManagedAccessYear($('#posAccessYear').val());
            }

            function syncManagedAccessYear(year = null) {
                if (!isFullAdminUserManagement || !$('#posAccessYear').length) {
                    return defaultManagedAccessYear;
                }

                const safeYear = normalizeManagedAccessYear(year);
                $('#posAccessYear').val(safeYear);
                updateManagedAccessYearStepper(safeYear);

                return safeYear;
            }

            function resetPositionFormMode() {
                const $fp = $('#formAddPos');
                $fp[0].reset();
                $fp.find('select').val(null).trigger('change');
                $fp.find('input[name=_method]').remove();
                $fp.data('action', currentUser?.addpos_url || '');
                $('#pos_form_mode').val('create');
                $('#positionFormModalTitle').text('Tambah Posisi');
                $('#positionFormModalSubtitle').text(
                    'Lengkapi data jabatan, scope kerja, masa berlaku, dan dokumen SK posisi user.'
                );
                $('#posFormTitle').text('Tambah Posisi');
                $('#posFormSubtitle').text('Lengkapi detail posisi baru sebelum disimpan ke user ini.');
                $('#btnSubmitPosForm')
                    .removeClass('btn-primary')
                    .addClass('btn-success');
                $('#btnSubmitPosLabel').text('Tambah Posisi');
                $('#btnSubmitPosForm i')
                    .removeClass('fa-floppy-disk')
                    .addClass('fa-plus');
                $('#btnCancelEditPos').addClass('d-none');
                $('#pos_file_helper').addClass('d-none').text('');
                $fp.find('[name=document_type]').val(defaultPositionDocumentType).trigger('change');
                $fp.find('[name=document_number]').val('');
                $fp.find('[name=document_date]').val('');
                $fp.find('[name=issued_by]').val('');
                $fp.find('[name=notes]').val('');
                $fp.find('[name=started_at]').val(localDateInputValue());
            }

            function renderPositionUserHeader(userPayload = null) {
                const name = blank(userPayload?.nama || userPayload?.nik, '-');
                const nik = blank(userPayload?.nik, '-');
                const email = blank(userPayload?.email, 'Email belum diisi');
                const accountType = userAccountTypeLabels[userPayload?.account_type] ||
                    blank(userPayload?.account_type, 'Tipe akun -');
                const status = userAccountStatusLabels[userPayload?.status] || blank(userPayload?.status, 'Status -');
                const statusIcons = {
                    active: 'fa-circle-check',
                    pending: 'fa-clock',
                    inactive: 'fa-circle-minus',
                    locked: 'fa-lock',
                    suspended: 'fa-ban',
                };
                const year = userPayload?.tahun_aktif ? `Tahun ${userPayload.tahun_aktif}` : 'Tahun -';

                $('#posUserName').text(name);
                $('#posHeaderNik').text(`NIK ${nik}`);
                $('#posHeaderEmail').text(email);
                $('#posHeaderAccountType').text(accountType);
                $('#posHeaderStatus').text(status);
                $('#posHeaderStatusIcon').attr('class', `fa ${statusIcons[userPayload?.status] || 'fa-circle-info'}`);
                $('#posHeaderYear').text(year);
            }

            function renderActivePositionLoading() {
                $('#activePositionPanel')
                    .attr('class', 'users-active-position-panel users-active-position-panel--loading')
                    .html(`
                        <div class="users-active-position-top">
                            <div class="users-active-position-title-wrap">
                                <span class="users-active-position-icon">
                                    <i class="fa fa-circle-notch fa-spin"></i>
                                </span>
                                <div>
                                    <div class="users-active-position-eyebrow">Posisi Aktif Saat Ini</div>
                                    <h6 class="users-active-position-title">Memuat posisi aktif...</h6>
                                    <p class="users-active-position-subtitle">
                                        Ringkasan posisi aktif akan tampil setelah data posisi selesai dimuat.
                                    </p>
                                </div>
                            </div>
                        </div>
                    `);
            }

            function renderActivePositionEmpty(totalPositions = 0) {
                const helper = totalPositions > 0 ?
                    'User sudah memiliki riwayat posisi, tetapi belum ada posisi yang sedang aktif.' :
                    'User belum memiliki posisi. Gunakan tombol Tambah Posisi untuk membuat posisi pertama.';

                $('#activePositionPanel')
                    .attr('class', 'users-active-position-panel users-active-position-panel--empty')
                    .html(`
                        <div class="users-active-position-top">
                            <div class="users-active-position-title-wrap">
                                <span class="users-active-position-icon">
                                    <i class="fa fa-triangle-exclamation"></i>
                                </span>
                                <div>
                                    <div class="users-active-position-eyebrow">Posisi Aktif Saat Ini</div>
                                    <h6 class="users-active-position-title">Belum ada posisi aktif</h6>
                                    <p class="users-active-position-subtitle">${helper}</p>
                                </div>
                            </div>
                            <div class="users-active-position-badges">
                                <span class="users-status-badge users-status-badge--inactive">
                                    <i class="fa fa-clock-rotate-left"></i>Tidak Aktif
                                </span>
                            </div>
                        </div>
                    `);
            }

            function renderActivePositionError() {
                $('#activePositionPanel')
                    .attr('class', 'users-active-position-panel users-active-position-panel--empty')
                    .html(`
                        <div class="users-active-position-top">
                            <div class="users-active-position-title-wrap">
                                <span class="users-active-position-icon">
                                    <i class="fa fa-circle-exclamation"></i>
                                </span>
                                <div>
                                    <div class="users-active-position-eyebrow">Posisi Aktif Saat Ini</div>
                                    <h6 class="users-active-position-title">Gagal memuat posisi aktif</h6>
                                    <p class="users-active-position-subtitle">
                                        Data posisi belum berhasil dibaca. Tutup modal lalu buka kembali bila koneksi sudah normal.
                                    </p>
                                </div>
                            </div>
                            <div class="users-active-position-badges">
                                <span class="users-status-badge users-status-badge--inactive">
                                    <i class="fa fa-circle-exclamation"></i>Error
                                </span>
                            </div>
                        </div>
                    `);
            }

            function renderActivePositionPanel(rows) {
                const active = rows.find((row) => !!row.is_active);

                if (!active) {
                    renderActivePositionEmpty(rows.length);
                    return;
                }

                const positionKey = String(active.id_enc || '');
                const jabatanLabel = escapeHtml(active.jabatan || '-');
                const instansiLabel = escapeHtml(active.instansi || '-');
                const unitLabel = escapeHtml(active.unit || '-');
                const startedAt = active.started_at ? escapeHtml(active.started_at) : 'Tanggal mulai belum diisi';
                const endedAt = active.ended_at ? escapeHtml(active.ended_at) : 'Masih berjalan';
                const documentNumber = escapeHtml(active.document_number || 'Nomor SK belum diisi');
                const documentDate = active.document_date ? `Tanggal ${escapeHtml(active.document_date)}` :
                    'Tanggal SK belum diisi';
                const documentType = escapeHtml(active.document_type_label || 'Dokumen SK');
                const issuedBy = escapeHtml(active.issued_by || 'Penerbit belum diisi');
                const historicalYear = Number(active.historical_year || managedAccessCurrentYear);
                const historicalIsCurrent = isCurrentManagedAccessYear(historicalYear);
                const historicalAccessState = historicalIsCurrent ? 'Mode tulis standar' :
                    (active.historical_access_granted ? 'Izin tulis aktif' : 'Mode lihat saja');
                const historicalAccessLabel = escapeHtml(active.historical_access_label || '-');
                const fileAction = active.file_url ?
                    `<a href="${escapeHtml(active.file_url)}" target="_blank" class="btn btn-sm btn-outline-secondary" title="Lihat file SK">
                        <i class="fa fa-file-lines"></i>Lihat SK
                    </a>` :
                    '';
                const editAction = positionKey && active.show_url ?
                    `<button class="btn btn-sm btn-outline-primary btnEditPos" data-position-key="${escapeHtml(positionKey)}" title="Edit posisi aktif">
                        <i class="fa fa-pen"></i>Edit Posisi
                    </button>` :
                    '';
                const deactivateAction = positionKey && active.deactivate_url ?
                    `<button class="btn btn-sm btn-outline-warning btnDeactivatePos" data-position-key="${escapeHtml(positionKey)}" title="Nonaktifkan posisi aktif">
                        <i class="fa fa-user-slash"></i>Nonaktifkan
                    </button>` :
                    '';
                const accessPill = historicalIsCurrent ?
                    '<span class="users-access-pill users-access-pill--current"><i class="fa fa-pen-to-square"></i>Tahun berjalan</span>' :
                    (active.historical_access_granted ?
                        `<span class="users-access-pill users-access-pill--granted"><i class="fa fa-lock-open"></i>Izin tulis ${historicalYear} aktif</span>` :
                        `<span class="users-access-pill users-access-pill--readonly"><i class="fa fa-lock"></i>Mode lihat saja ${historicalYear}</span>`);

                $('#activePositionPanel')
                    .attr('class', 'users-active-position-panel')
                    .html(`
                        <div class="users-active-position-top">
                            <div class="users-active-position-title-wrap">
                                <span class="users-active-position-icon">
                                    <i class="fa fa-briefcase"></i>
                                </span>
                                <div>
                                    <div class="users-active-position-eyebrow">
                                        <i class="fa fa-circle-check"></i>Posisi Aktif Saat Ini
                                    </div>
                                    <h6 class="users-active-position-title">${jabatanLabel}</h6>
                                    <p class="users-active-position-subtitle">${unitLabel} - ${instansiLabel}</p>
                                </div>
                            </div>
                            <div class="users-active-position-badges">
                                <span class="users-status-badge users-status-badge--active">
                                    <i class="fa fa-circle-check"></i>Aktif
                                </span>
                                ${accessPill}
                            </div>
                        </div>
                        <div class="users-active-position-grid">
                            <div class="users-active-position-field">
                                <div class="users-active-position-label">
                                    <i class="fa fa-sitemap"></i>Scope
                                </div>
                                <div class="users-active-position-value">${unitLabel}</div>
                                <div class="users-active-position-helper">${instansiLabel}</div>
                            </div>
                            <div class="users-active-position-field">
                                <div class="users-active-position-label">
                                    <i class="fa fa-calendar-days"></i>Periode
                                </div>
                                <div class="users-active-position-value">${startedAt}</div>
                                <div class="users-active-position-helper">Selesai: ${endedAt}</div>
                            </div>
                            <div class="users-active-position-field">
                                <div class="users-active-position-label">
                                    <i class="fa fa-file-signature"></i>Dokumen SK
                                </div>
                                <div class="users-active-position-value">${documentNumber}</div>
                                <div class="users-active-position-helper">${documentType} - ${documentDate}</div>
                            </div>
                            <div class="users-active-position-field">
                                <div class="users-active-position-label">
                                    <i class="fa fa-user-shield"></i>Akses ${historicalYear}
                                </div>
                                <div class="users-active-position-value">${historicalAccessState}</div>
                                <div class="users-active-position-helper">${historicalAccessLabel}</div>
                            </div>
                        </div>
                        <div class="users-active-position-actions">
                            <span class="users-muted-pill">
                                <i class="fa fa-building"></i>${issuedBy}
                            </span>
                            ${fileAction}
                            ${editAction}
                            ${deactivateAction}
                        </div>
                    `);
            }

            function enterEditPositionMode(position) {
                const $fp = $('#formAddPos');
                resetPositionFormMode();

                $('#pos_form_mode').val('edit');
                $('#positionFormModalTitle').text('Edit Posisi');
                $('#positionFormModalSubtitle').text('Perbarui posisi user tanpa mengubah profil akun utama.');
                $('#posFormTitle').text('Edit Posisi');
                $('#posFormSubtitle').text('Perbarui detail posisi ini tanpa perlu menghapus dan menambah ulang.');
                $('#btnSubmitPosForm')
                    .removeClass('btn-success')
                    .addClass('btn-primary');
                $('#btnSubmitPosLabel').text('Simpan Perubahan');
                $('#btnSubmitPosForm i')
                    .removeClass('fa-plus')
                    .addClass('fa-floppy-disk');
                $('#btnCancelEditPos').removeClass('d-none');
                $fp.data('action', position.update_url);
                $fp.prepend('<input type="hidden" name="_method" value="PUT">');
                if (!$('#pos_jabatan').find(`option[value="${position.jabatan_id}"]`).length) {
                    $('#pos_jabatan').append(new Option(position.jabatan || 'Jabatan', position.jabatan_id));
                }
                $('#pos_jabatan').val(position.jabatan_id).trigger('change.select2');

                if (position.file_name) {
                    $('#pos_file_helper')
                        .removeClass('d-none')
                        .text(`File SK saat ini: ${position.file_name}. Upload file baru jika ingin mengganti.`);
                }

                $fp.find('[name=started_at]').val(position.started_at || '');
                $fp.find('[name=ended_at]').val(position.ended_at || '');
                $fp.find('[name=document_type]').val(position.document_type || defaultPositionDocumentType).trigger('change');
                $fp.find('[name=document_number]').val(position.document_number || '');
                $fp.find('[name=document_date]').val(position.document_date || '');
                $fp.find('[name=issued_by]').val(position.issued_by || '');
                $fp.find('[name=notes]').val(position.notes || '');
                $('#posActive').prop('checked', !!position.is_active);

                loadPositionInstansiOptions(position.jabatan_id, {
                        id: position.instansi_id,
                        text: position.instansi,
                    })
                    .done(function() {
                        return loadPositionUnitOptions(position.instansi_id, position.jabatan_id, {
                            id: position.unit_kerja_id,
                            text: position.unit,
                        });
                    })
                    .fail(function() {
                        Swal.fire('Error', 'Gagal menyiapkan data posisi untuk diedit.', 'error');
                    });
            }

            function openPositionFormModal() {
                if (!currentUser) {
                    Swal.fire('Error', 'Data user belum siap. Tutup modal lalu buka kembali Kelola Posisi.', 'error');
                    return;
                }

                initSelect2('#modalPositionForm');
                restorePositionsModalAfterForm = true;

                if (!$('#modalPositions').hasClass('show')) {
                    modalPositionForm.show();
                    return;
                }

                preservePositionsModalState = true;
                document.getElementById('modalPositions').addEventListener('hidden.bs.modal', function showFormModal() {
                    preservePositionsModalState = false;
                    modalPositionForm.show();
                }, {
                    once: true
                });
                modalPositions.hide();
            }

            function openManagePositionsForUser(userPayload) {
                currentUser = userPayload;
                if (!currentUser) return;

                renderPositionUserHeader(currentUser);
                renderActivePositionLoading();
                syncManagedAccessYear();
                resetPositionFormMode();
                $('#formAddPos').data('action', currentUser.addpos_url);
                updateHistoricalAccessSummary();
                loadPositionsTable();
                modalPositions.show();
            }

            $('#btnCreateUser').on('click', function() {
                if (window.readOnlyUI?.guardAction()) return;
                const $f = $('#formUser');
                initSelect2('#modalUser');
                setUserModalMode('create');
                $f[0].reset();
                $f.data('mode', 'create');
                $f.data('action', @json(route('users.store')));
                $f.find('input[name=_method]').remove();
                resetTemporaryLockedStatusOption();
                $f.find('[name=account_type]').val(@json(\App\Models\User::ACCOUNT_TYPE_PERSONAL)).trigger('change');
                $f.find('[name=status]').val(@json(\App\Models\User::STATUS_ACTIVE)).trigger('change');
                $f.find('[name=status_reason]').val('');
                $f.find('[name=tahun_aktif]').val(@json((int) session('tahun_aktif', now()->year)));
                syncUserStatusReasonRequirement();
                modalUser.show();
            });

            $('#users-table').on('click', '.btnEditUser', function() {
                if (window.readOnlyUI?.guardAction()) return;
                const data = $(this).data('user');
                const $f = $('#formUser');
                initSelect2('#modalUser');
                setUserModalMode('edit');
                $f[0].reset();

                $f.data('mode', 'edit');
                $f.data('action', data.update_url);
                if (!$f.find('input[name=_method]').length) {
                    $f.prepend('<input type="hidden" name="_method" value="PUT">');
                }

                syncTemporaryLockedStatusOption(data.status);
                $f.find('[name=nik]').val(data.nik);
                $f.find('[name=nip]').val(data.nip);
                $f.find('[name=nama]').val(data.nama);
                $f.find('[name=email]').val(data.email);
                $f.find('[name=account_type]').val(data.account_type || @json(\App\Models\User::ACCOUNT_TYPE_PERSONAL)).trigger('change');
                $f.find('[name=status]').val(data.status || @json(\App\Models\User::STATUS_ACTIVE)).trigger('change');
                $f.find('[name=status_reason]').val(data.status_reason || '');
                $f.find('[name=tahun_aktif]').val(data.tahun_aktif || '');
                $f.find('[name=password]').val('');
                $f.find('[name=old_password]').val('');
                syncUserStatusReasonRequirement();
                modalUser.show();
            });

            $('#user_status').on('change', syncUserStatusReasonRequirement);

            $('#formUser').on('submit', function(e) {
                e.preventDefault();
                if (window.readOnlyUI?.guardAction()) return;
                const $f = $(this);
                const action = $f.data('action') || @json(route('users.store'));
                const formData = $f.serialize();

                $.post(action, formData)
                    .done(function(resp) {
                        const shouldOpenPositionSetup = !!resp.prompt_position_setup && !!resp.user;

                        if (shouldOpenPositionSetup) {
                            $('#modalUser').one('hidden.bs.modal', function() {
                                dt.ajax.reload(null, false);
                                Swal.fire({
                                    toast: true,
                                    position: 'top-end',
                                    icon: 'success',
                                    title: resp.message || 'User dibuat.',
                                    showConfirmButton: false,
                                    timer: 1600,
                                    timerProgressBar: true,
                                });
                                openManagePositionsForUser(resp.user);
                            });
                            modalUser.hide();

                            return;
                        }

                        modalUser.hide();
                        Swal.fire('Sukses', resp.message || 'Tersimpan.', 'success');
                        dt.ajax.reload(null, false);
                    })
                    .fail(function(xhr) {
                        let msg = 'Gagal menyimpan.';
                        if (xhr.responseJSON?.errors) {
                            const firstFieldErrors = Object.values(xhr.responseJSON.errors)[0];
                            if (Array.isArray(firstFieldErrors) && firstFieldErrors.length) {
                                msg = firstFieldErrors[0];
                            }
                        } else if (xhr.responseJSON && xhr.responseJSON.message) {
                            msg = xhr.responseJSON.message;
                        }
                        Swal.fire('Error', msg, 'error');
                    });
            });

            let deleteAction = null;
            $('#users-table').on('click', '.btnDeleteUser', function() {
                if (window.readOnlyUI?.guardAction()) return;
                const data = $(this).data('user');
                deleteAction = data.destroy_url;
                $('#delUserName').text(data.nama || data.nik);
                modalDelete.show();
            });

            $('#formDeleteUser').on('submit', function(e) {
                e.preventDefault();
                if (window.readOnlyUI?.guardAction()) return;
                if (!deleteAction) return;
                $.post(deleteAction, {
                        _method: 'DELETE'
                    })
                    .done(function(resp) {
                        modalDelete.hide();
                        Swal.fire('Terhapus', resp.message || 'User dihapus.', 'success');
                        dt.ajax.reload(null, false);
                    })
                    .fail(function() {
                        Swal.fire('Error', 'Gagal menghapus.', 'error');
                    });
            });

            let userSecuritySourceUser = null;
            let userSecurityState = null;
            let userSecurityAction = null;

            function resetUserSecurityModal(userPayload = null) {
                const name = userPayload?.nama || userPayload?.nik || '-';
                const nik = userPayload?.nik ? `(${userPayload.nik})` : '';

                $('#securityUserName').text(name);
                $('#securityUserNik').text(nik);
                resetSecuritySignals();
                $('#securityStatusBadge')
                    .attr('class', 'badge bg-gradient-secondary')
                    .text('Memuat...');
                $('#securityMfaBadge')
                    .attr('class', 'badge bg-gradient-secondary')
                    .text('Memuat...');
                $('#securityStatusChanged, #securityStatusReason, #securityLockedAt, #securityLockedUntil, #securityLockReason')
                    .text('-');
                $('#securityPasswordState, #securityPasswordChanged, #securityPasswordResetAt, #securityPasswordResetBy')
                    .text('-');
                $('#securityMfaEnabled, #securityMfaRecoveryCount, #securityMfaLastUsed')
                    .text('-');
                $('#securityLastLogin, #securityFailedLogin, #securitySessionInvalidated')
                    .text('-');
                setSecurityButton('#btnSecurityForcePassword', false, 'Memuat status keamanan akun.');
                setSecurityButton('#btnSecurityResetPassword', false, 'Memuat status keamanan akun.');
                setSecurityButton('#btnSecurityLock', false, 'Memuat status keamanan akun.');
                setSecurityButton('#btnSecurityUnlock', false, 'Memuat status keamanan akun.');
                setSecurityButton('#btnSecurityResetMfa', false, 'Memuat status keamanan akun.');
            }

            function setSecurityButton(selector, enabled, disabledReason = '') {
                $(selector)
                    .prop('disabled', !enabled)
                    .attr('title', enabled ? '' : disabledReason);
            }

            function setSecuritySignal(selector, variant, iconClass, value) {
                const allowedVariants = ['success', 'warning', 'danger', 'muted'];
                const normalizedVariant = allowedVariants.includes(variant) ? variant : 'muted';
                const $signal = $(selector);

                $signal
                    .removeClass('users-security-signal--success users-security-signal--warning users-security-signal--danger users-security-signal--muted')
                    .addClass(`users-security-signal--${normalizedVariant}`);
                $signal.find('.users-security-signal__icon i').attr('class', `fa ${iconClass}`);
                $signal.find('.users-security-signal__value').text(value || '-');
            }

            function resetSecuritySignals() {
                setSecuritySignal('#securityAccountSignal', 'muted', 'fa-circle-notch', 'Memuat...');
                setSecuritySignal('#securityPasswordSignal', 'muted', 'fa-circle-notch', 'Memuat...');
                setSecuritySignal('#securityMfaSignal', 'muted', 'fa-circle-notch', 'Memuat...');
            }

            function renderUserSecurity(data) {
                userSecurityState = data || null;
                if (!userSecurityState) return;

                const status = userSecurityState.status || {};
                const password = userSecurityState.password || {};
                const mfa = userSecurityState.mfa || {};
                const activity = userSecurityState.activity || {};
                const permissions = userSecurityState.permissions || {};

                $('#securityUserName').text(blank(userSecurityState.nama));
                $('#securityUserNik').text(userSecurityState.nik ? `(${userSecurityState.nik})` : '');
                $('#securityStatusBadge')
                    .attr('class', `badge ${status.badge_class || 'bg-gradient-secondary'}`)
                    .text(blank(status.label));
                setSecuritySignal(
                    '#securityAccountSignal',
                    status.is_locked ? 'danger' : (status.value === 'active' ? 'success' : 'warning'),
                    status.is_locked ? 'fa-lock' : (status.value === 'active' ? 'fa-circle-check' : 'fa-circle-exclamation'),
                    status.is_locked ? 'Terkunci' : blank(status.label)
                );
                $('#securityStatusChanged').text(status.changed_at && status.changed_at !== '-' ?
                    `Diubah ${status.changed_at}${status.changed_by ? ` oleh ${status.changed_by}` : ''}` :
                    'Belum ada perubahan status tercatat.');
                $('#securityStatusReason').text(blank(status.reason));
                $('#securityLockedAt').text(blank(status.locked_at));
                $('#securityLockedUntil').text(blank(status.locked_until));
                $('#securityLockReason').text(status.lock_reason ? `Alasan kunci: ${status.lock_reason}` :
                    'Tidak ada alasan kunci tersimpan.');

                const passwordState = password.has_expired ? 'Password kedaluwarsa' :
                    (password.must_change ? 'Wajib ganti password' : 'Normal');
                setSecuritySignal(
                    '#securityPasswordSignal',
                    password.requires_change ? 'warning' : 'success',
                    password.requires_change ? 'fa-key' : 'fa-circle-check',
                    passwordState
                );
                $('#securityPasswordState').text(passwordState);
                $('#securityPasswordChanged').text(`Diubah: ${blank(password.changed_at)} | Kedaluwarsa: ${blank(password.expires_at)}`);
                $('#securityPasswordResetAt').text(blank(password.reset_at));
                $('#securityPasswordResetBy').text(password.reset_by ? `Oleh ${password.reset_by}` :
                    'Belum ada reset administratif.');

                $('#securityMfaBadge')
                    .attr('class', `badge ${mfa.badge_class || 'bg-gradient-secondary'}`)
                    .text(blank(mfa.label));
                setSecuritySignal(
                    '#securityMfaSignal',
                    mfa.is_enrolled ? 'success' : (mfa.has_pending_enrollment ? 'warning' : 'muted'),
                    mfa.is_enrolled ? 'fa-shield-halved' : (mfa.has_pending_enrollment ? 'fa-clock' : 'fa-shield'),
                    blank(mfa.label)
                );
                $('#securityMfaEnabled').text(`Aktif: ${blank(mfa.enabled_at)} | Konfirmasi: ${blank(mfa.confirmed_at)}`);
                $('#securityMfaRecoveryCount').text(`${formatNumber(mfa.recovery_codes_remaining || 0)} kode tersimpan`);
                $('#securityMfaLastUsed').text(`Terakhir dipakai: ${blank(mfa.last_used_at)}`);

                $('#securityLastLogin').text(blank(activity.last_login_at));
                $('#securityFailedLogin').text(`${formatNumber(activity.failed_login_count || 0)} percobaan gagal`);
                $('#securitySessionInvalidated').text(`Session invalidated: ${blank(activity.sessions_invalidated_at)}`);

                setSecurityButton(
                    '#btnSecurityForcePassword',
                    !!permissions.can_force_password_change,
                    permissions.can_manage_security ? 'Password sudah ditandai wajib ganti.' :
                    'Anda tidak berwenang menjalankan aksi ini.'
                );
                setSecurityButton(
                    '#btnSecurityResetPassword',
                    !!permissions.can_reset_password,
                    permissions.can_manage_security ? 'Reset password tidak tersedia untuk akun ini.' :
                    'Anda tidak berwenang menjalankan aksi ini.'
                );
                setSecurityButton(
                    '#btnSecurityLock',
                    !!permissions.can_lock,
                    permissions.can_manage_security ? 'Akun harus aktif dan belum terkunci untuk dikunci.' :
                    'Anda tidak berwenang menjalankan aksi ini.'
                );
                setSecurityButton(
                    '#btnSecurityUnlock',
                    !!permissions.can_unlock,
                    permissions.can_manage_security ? 'Akun tidak sedang terkunci.' :
                    'Anda tidak berwenang menjalankan aksi ini.'
                );
                setSecurityButton(
                    '#btnSecurityResetMfa',
                    !!permissions.can_reset_mfa,
                    permissions.can_manage_security ? 'MFA belum aktif atau reset MFA hanya untuk Admin Super.' :
                    'Anda tidak berwenang menjalankan aksi ini.'
                );
            }

            function loadUserSecurity(userPayload) {
                if (!userPayload?.security_url) {
                    Swal.fire('Error', 'Endpoint keamanan akun tidak ditemukan.', 'error');
                    return;
                }

                userSecuritySourceUser = userPayload;
                resetUserSecurityModal(userPayload);
                modalUserSecurity.show();

                $.get(userPayload.security_url)
                    .done(function(resp) {
                        renderUserSecurity(resp.data);
                    })
                    .fail(function(xhr) {
                        Swal.fire('Error', ajaxErrorMessage(xhr, 'Gagal memuat keamanan akun.'), 'error');
                        modalUserSecurity.hide();
                    });
            }

            function securityActionConfig(action) {
                const urls = userSecurityState?.urls || {};

                return {
                    force_password: {
                        url: urls.force_password_change,
                        title: 'Force Change Password',
                        subtitle: 'Tandai user wajib mengganti password saat login berikutnya.',
                        warning: 'Session aktif user akan dibatalkan setelah aksi ini disimpan.',
                        submitClass: 'btn-primary',
                        submitHtml: '<i class="fa fa-key me-1"></i>Tandai Wajib Ganti',
                    },
                    reset_password: {
                        url: urls.reset_password,
                        title: 'Reset Password',
                        subtitle: 'Buat password sementara baru untuk user ini.',
                        warning: 'Password lama tidak akan berlaku, session aktif user akan dicabut, dan password sementara hanya tampil satu kali setelah aksi berhasil.',
                        submitClass: 'btn-danger',
                        submitHtml: '<i class="fa fa-rotate-right me-1"></i>Reset Password',
                    },
                    lock: {
                        url: urls.lock,
                        title: 'Lock Account',
                        subtitle: 'Kunci akun user dan batalkan session aktifnya.',
                        warning: 'User tidak dapat login sampai akun dibuka kembali atau batas waktu kunci berakhir.',
                        submitClass: 'btn-danger',
                        submitHtml: '<i class="fa fa-lock me-1"></i>Kunci Akun',
                    },
                    unlock: {
                        url: urls.unlock,
                        title: 'Unlock Account',
                        subtitle: 'Buka kembali akun user yang sedang terkunci.',
                        warning: 'Counter gagal login akan direset dan user dapat login kembali.',
                        submitClass: 'btn-success',
                        submitHtml: '<i class="fa fa-lock-open me-1"></i>Buka Kunci',
                    },
                    reset_mfa: {
                        url: urls.reset_mfa,
                        title: 'Reset MFA',
                        subtitle: 'Hapus konfigurasi MFA user dan batalkan session aktifnya.',
                        warning: 'User harus melakukan setup MFA ulang bila kebijakan akunnya mewajibkan MFA.',
                        submitClass: 'btn-warning',
                        submitHtml: '<i class="fa fa-mobile-screen-button me-1"></i>Reset MFA',
                    },
                } [action] || null;
            }

            function openUserSecurityReasonModal(action) {
                const config = securityActionConfig(action);
                if (!config?.url) {
                    Swal.fire('Error', 'Endpoint aksi keamanan tidak tersedia.', 'error');
                    return;
                }

                userSecurityAction = {
                    action,
                    url: config.url,
                };

                $('#securityReasonTitle').text(config.title);
                $('#securityReasonSubtitle').text(config.subtitle);
                $('#securityReasonWarning').text(config.warning);
                $('#securityReasonInput').val('');
                $('#securityLockedUntilWrap').toggleClass('d-none', action !== 'lock');
                $('#securityLockedUntilInput')
                    .attr('min', localDateTimeInputValue())
                    .val('');
                $('#securityReasonSubmit')
                    .removeClass('btn-primary btn-danger btn-success btn-warning')
                    .addClass(config.submitClass)
                    .html(config.submitHtml);
                modalUserSecurityReason.show();
                setTimeout(() => $('#securityReasonInput').trigger('focus'), 180);
            }

            function showTemporaryPasswordResult(resp) {
                const temporaryPassword = String(resp?.temporary_password || '');

                if (!temporaryPassword) {
                    Swal.fire('Sukses', resp?.message || 'Password user berhasil direset.', 'success');
                    return;
                }

                const userName = userSecurityState?.nama || userSecuritySourceUser?.nama || userSecuritySourceUser?.nik || '-';

                $('#securityPasswordResultUserName').text(`Untuk ${userName}`);
                $('#securityTemporaryPasswordValue').text(temporaryPassword);
                $('#securityTemporaryPasswordCopyStatus')
                    .removeClass('users-temporary-password-status--copied')
                    .html('<i class="fa fa-clock"></i>Belum disalin');
                $('#btnCopyTemporaryPassword')
                    .data('temporary-password', temporaryPassword)
                    .removeClass('btn-success')
                    .addClass('btn-primary');
                $('#btnCopyTemporaryPassword i')
                    .attr('class', 'fa fa-copy');
                $('#btnCopyTemporaryPasswordLabel').text('Salin');
                modalUserSecurityPasswordResult.show();
            }

            function copyTextToClipboard(text) {
                if (navigator.clipboard && window.isSecureContext) {
                    return navigator.clipboard.writeText(text);
                }

                const textarea = document.createElement('textarea');
                textarea.value = text;
                textarea.setAttribute('readonly', 'readonly');
                textarea.style.position = 'fixed';
                textarea.style.opacity = '0';
                document.body.appendChild(textarea);
                textarea.select();

                return new Promise((resolve, reject) => {
                    try {
                        document.execCommand('copy') ? resolve() : reject(new Error('Copy command failed.'));
                    } catch (error) {
                        reject(error);
                    } finally {
                        document.body.removeChild(textarea);
                    }
                });
            }

            $('#users-table').on('click', '.btnManageSecurity', function() {
                if (window.readOnlyUI?.guardAction()) return;
                const data = $(this).data('user');
                loadUserSecurity(data);
            });

            $('#btnSecurityForcePassword').on('click', function() {
                openUserSecurityReasonModal('force_password');
            });

            $('#btnSecurityResetPassword').on('click', function() {
                openUserSecurityReasonModal('reset_password');
            });

            $('#btnSecurityLock').on('click', function() {
                openUserSecurityReasonModal('lock');
            });

            $('#btnSecurityUnlock').on('click', function() {
                openUserSecurityReasonModal('unlock');
            });

            $('#btnSecurityResetMfa').on('click', function() {
                openUserSecurityReasonModal('reset_mfa');
            });

            $('#btnCopyTemporaryPassword').on('click', function() {
                const temporaryPassword = String($(this).data('temporary-password') || '');

                if (!temporaryPassword) {
                    Swal.fire('Error', 'Password sementara tidak tersedia.', 'error');
                    return;
                }

                copyTextToClipboard(temporaryPassword)
                    .then(() => {
                        $('#securityTemporaryPasswordCopyStatus')
                            .addClass('users-temporary-password-status--copied')
                            .html('<i class="fa fa-check"></i>Tersalin');
                        $(this)
                            .removeClass('btn-primary')
                            .addClass('btn-success');
                        $('#btnCopyTemporaryPassword i')
                            .attr('class', 'fa fa-check');
                        $('#btnCopyTemporaryPasswordLabel').text('Tersalin');
                    })
                    .catch(() => {
                        Swal.fire('Error', 'Gagal menyalin password sementara.', 'error');
                    });
            });

            $('#formUserSecurityReason').on('submit', function(e) {
                e.preventDefault();
                if (window.readOnlyUI?.guardAction()) return;
                if (!userSecurityAction?.url) return;

                const reason = String($('#securityReasonInput').val() || '').trim();
                if (!reason) {
                    $('#securityReasonInput').trigger('focus');
                    Swal.fire('Alasan wajib diisi', 'Isi alasan administratif sebelum melanjutkan.', 'warning');
                    return;
                }

                const payload = {
                    reason,
                };

                if (userSecurityAction.action === 'lock') {
                    payload.locked_until = $('#securityLockedUntilInput').val();
                }

                const submittedAction = userSecurityAction.action;

                $.post(userSecurityAction.url, payload)
                    .done(function(resp) {
                        const showActionResult = function() {
                            if (submittedAction === 'reset_password') {
                                showTemporaryPasswordResult(resp);
                                return;
                            }

                            Swal.fire('Sukses', resp.message || 'Aksi keamanan akun berhasil diproses.', 'success');
                        };

                        $('#modalUserSecurityReason').one('hidden.bs.modal', showActionResult);
                        modalUserSecurityReason.hide();

                        if (resp.data) {
                            renderUserSecurity(resp.data);
                        } else if (userSecuritySourceUser) {
                            loadUserSecurity(userSecuritySourceUser);
                        }
                        dt.ajax.reload(null, false);
                    })
                    .fail(function(xhr) {
                        Swal.fire('Error', ajaxErrorMessage(xhr, 'Gagal memproses aksi keamanan akun.'), 'error');
                    });
            });

            let currentUser = null;
            let deactivatePositionAction = null;
            let historicalReasonAction = null;
            let preservePositionsModalState = false;
            let restorePositionsModalAfterForm = false;
            const positionCache = {};

            function openDeactivatePositionModal(position) {
                deactivatePositionAction = position;

                const today = localDateInputValue();
                $('#deactivatePositionActionUrl').val(position.deactivate_url || '');
                $('#deactivatePositionName').text(`Posisi ${position.jabatan || '-'} akan dinonaktifkan.`);
                $('#deactivatePositionEndedAt')
                    .attr('max', today)
                    .val(position.ended_at || today);
                $('#deactivatePositionReason').val('');
                modalDeactivatePosition.show();
                setTimeout(() => $('#deactivatePositionReason').trigger('focus'), 180);
            }

            function openHistoricalReasonModal(mode, url, year) {
                historicalReasonAction = {
                    mode,
                    url,
                    year,
                };

                const isGrant = mode === 'grant';
                const actionLabel = isGrant ? 'Buka akses tulis' : 'Cabut akses tulis';
                const impactLabel = isGrant ?
                    'Posisi dapat menulis pada tahun historis ini.' :
                    'Posisi kembali ke mode lihat saja.';

                $('#historicalReasonTitle').text(isGrant ? `Izinkan Tulis Tahun ${year}` : `Cabut Izin Tahun ${year}`);
                $('#historicalReasonSubtitle').text(isGrant ?
                    'Berikan alasan administratif untuk membuka akses tulis pada tahun historis ini.' :
                    'Berikan alasan administratif untuk mencabut akses tulis tahun historis ini.'
                );
                $('#historicalReasonIcon')
                    .toggleClass('users-historical-icon--revoke', !isGrant);
                $('#historicalReasonIconSymbol')
                    .attr('class', isGrant ? 'fa fa-user-shield' : 'fa fa-user-lock');
                $('#historicalReasonYearLabel').text(year);
                $('#historicalReasonActionLabel').text(actionLabel);
                $('#historicalReasonImpactLabel').text(impactLabel);
                $('#historicalGrantMetadata').toggleClass('d-none', !isGrant);
                $('#historicalReasonSubmit')
                    .toggleClass('btn-primary', isGrant)
                    .toggleClass('btn-warning', !isGrant)
                    .html(isGrant ?
                        '<i class="fa fa-user-shield me-1"></i>Izinkan Tulis' :
                        '<i class="fa fa-user-lock me-1"></i>Cabut Izin'
                    );
                $('#historicalReasonActionUrl').val(url);
                $('#historicalReasonMode').val(mode);
                $('#historicalReasonYear').val(year);
                $('#historicalReasonInput').val('');
                $('#historicalReferenceNumber').val('');
                $('#historicalReferenceDate').val('');
                $('#historicalValidUntil').attr('min', localDateInputValue()).val('');
                $('#historicalGrantNotes').val('');
                modalHistoricalReason.show();
                setTimeout(() => $('#historicalReasonInput').trigger('focus'), 180);
            }

            function updatePositionSummary(rows) {
                const total = rows.length;
                const active = rows.find((row) => !!row.is_active);

                $('#posCount').text(`${formatNumber(total)} posisi`);
                $('#posActiveLabel').text(active ? `Posisi aktif: ${active.jabatan}` : 'Belum ada posisi aktif');
            }

            function renderPositionsEmptyState() {
                $('#positionCards').html(`
                    <div class="users-empty-state">
                        <i class="fa fa-briefcase"></i>
                        <div class="fw-semibold mb-1">Belum ada posisi untuk user ini.</div>
                        <div>Gunakan tombol Tambah Posisi untuk mulai mengelola akses user.</div>
                    </div>
                `);
            }

            function updateHistoricalAccessSummary() {
                const context = window.yearAccessContext || {};
                const year = isFullAdminUserManagement ? getManagedAccessYear() : (context.tahun_aktif ||
                    managedAccessCurrentYear);
                const currentYear = Number(context.tahun_sekarang || managedAccessCurrentYear);
                const isHistorical = !!context.is_historical;
                const hasOverride = !!context.has_historical_override;

                if (isFullAdminUserManagement) {
                    if (year === currentYear) {
                        $('#posYearAccessLabel').text(`Tahun ${year} - Tahun berjalan`);
                        $('#posHistoricalAccessHelper').html(
                            `Tahun <strong>${year}</strong> adalah tahun berjalan. Posisi aktif tidak memerlukan izin khusus untuk menulis, tetapi Anda tetap bisa berpindah ke tahun lain untuk menyiapkan akses historis.`
                        );
                        return;
                    }

                    $('#posYearAccessLabel').text(`Tahun ${year} - Kelola izin historis`);
                    $('#posHistoricalAccessHelper').html(
                        `Anda sedang mengatur <strong>izin edit historis</strong> untuk tahun <strong>${year}</strong>. Gunakan tombol <strong>Izinkan Tulis</strong> atau <strong>Cabut Izin</strong> pada posisi yang ingin diubah hak aksesnya.`
                    );
                    return;
                }

                if (!isHistorical) {
                    $('#posYearAccessLabel').text(`Tahun ${year} - Mode tulis standar`);
                    $('#posHistoricalAccessHelper').html(
                        `Tahun <strong>${year}</strong> adalah tahun berjalan, sehingga posisi aktif tetap dapat menulis tanpa izin tambahan.`
                    );
                    return;
                }

                if (hasOverride) {
                    $('#posYearAccessLabel').text(`Tahun ${year} - Izin tulis historis aktif`);
                    $('#posHistoricalAccessHelper').html(
                        `Konteks aktif saat ini memiliki <strong>izin tulis historis</strong> untuk tahun <strong>${year}</strong>. Admin Super tetap bisa mengelola izin posisi lain dari panel ini.`
                    );
                    return;
                }

                $('#posYearAccessLabel').text(`Tahun ${year} - Mode lihat saja`);
                $('#posHistoricalAccessHelper').html(
                    `Tahun <strong>${year}</strong> sedang dalam mode lihat saja. Admin Super dapat memberi <strong>izin tulis historis per posisi</strong> langsung dari daftar posisi user.`
                );
            }

            $('#users-table').on('click', '.btnManagePos', function() {
                const data = $(this).data('user');
                if (!data) return;
                openManagePositionsForUser(data);
            });

            $('#btnOpenPositionForm').on('click', function() {
                if (window.readOnlyUI?.guardAction()) return;
                resetPositionFormMode();
                openPositionFormModal();
            });

            function loadPositionsTable() {
                const $cards = $('#positionCards').empty();
                Object.keys(positionCache).forEach(function(key) {
                    delete positionCache[key];
                });
                return $.get(currentUser.pos_index_url, {
                        tahun: getManagedAccessYear()
                    })
                    .done(function(resp) {
                        const rows = (resp.data || []);
                        $('#formAddPos').find('button, select, input, textarea').prop('disabled', false);

                        rows.forEach(function(position) {
                            const positionKey = String(position.id_enc || '');

                            if (positionKey) {
                                positionCache[positionKey] = position;
                            }
                        });

                        updatePositionSummary(rows);
                        renderActivePositionPanel(rows);

                        if (!rows.length) {
                            renderPositionsEmptyState();
                            return;
                        }

                        $cards.html(`
                            <div class="users-position-table-wrap">
                                <table class="table users-position-table align-items-center">
                                    <thead>
                                        <tr>
                                            <th>Status</th>
                                            <th>Jabatan & Scope</th>
                                            <th>Periode</th>
                                            <th>Dokumen SK</th>
                                            <th>Akses Tahun</th>
                                            <th class="text-end">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        `);

                        const $tbody = $cards.find('tbody');

                        rows.forEach(function(p) {
                            const positionKey = String(p.id_enc || '');

                            if (!positionKey) {
                                return;
                            }

                            const jabatanLabel = escapeHtml(p.jabatan || '-');
                            const instansiLabel = escapeHtml(p.instansi || '-');
                            const unitLabel = escapeHtml(p.unit || '-');
                            const fileLink = p.file_url ?
                                `<a href="${escapeHtml(p.file_url)}" target="_blank" class="btn btn-xs btn-outline-secondary px-2 py-1 mb-0" title="Lihat file SK"><i class="fa fa-file-lines me-1"></i>Lihat SK</a>` :
                                '<span class="users-muted-pill">Belum ada</span>';
                            const documentTypeLabel = escapeHtml(p.document_type_label || '-');
                            const documentNumber = escapeHtml(p.document_number || '-');
                            const documentDate = escapeHtml(p.document_date || '-');
                            const issuedBy = escapeHtml(p.issued_by || '-');
                            const deactivationReason = String(p.deactivation_reason || '').trim();
                            const deactivationMeta = [
                                p.deactivated_at ? `Pada ${escapeHtml(p.deactivated_at)}` : null,
                                p.deactivated_by ? `oleh ${escapeHtml(p.deactivated_by)}` : null,
                            ].filter(Boolean).join(' ');
                            const deactivationSummary = (!p.is_active && (deactivationReason || deactivationMeta)) ?
                                `<div class="users-position-table-meta">${deactivationReason ? escapeHtml(deactivationReason) : 'Dinonaktifkan'}${deactivationMeta ? ` - ${deactivationMeta}` : ''}</div>` :
                                '';

                            const badge = p.is_active ?
                                '<span class="users-status-badge users-status-badge--active"><i class="fa fa-circle-check"></i>Aktif</span>' :
                                '<span class="users-status-badge users-status-badge--inactive"><i class="fa fa-clock-rotate-left"></i>Nonaktif</span>';

                            const btnActivate = p.is_active ? '' : `
                                <button type="button" class="dropdown-item btnActivatePos" data-url="${escapeHtml(p.activate_url || '')}" title="Jadikan aktif">
                                    <i class="fa fa-check"></i>Aktifkan
                                </button>`;

                            const btnDeactivate = p.is_active ? `
                                <button type="button" class="dropdown-item btnDeactivatePos" data-position-key="${escapeHtml(positionKey)}" title="Nonaktifkan posisi">
                                    <i class="fa fa-user-slash"></i>Nonaktifkan
                                </button>` : '';

                            const btnEdit = `
                                <button type="button" class="dropdown-item btnEditPos" data-position-key="${escapeHtml(positionKey)}" title="Edit posisi">
                                    <i class="fa fa-pen"></i>Edit
                                </button>`;

                            const btnDelete = `
                                <button type="button" class="dropdown-item text-danger btnDeletePos" data-url="${escapeHtml(p.destroy_url || '')}" title="Hapus posisi">
                                    <i class="fa fa-trash"></i>Hapus
                                </button>`;

                            const historicalYear = Number(p.historical_year || managedAccessCurrentYear);
                            const historicalIsCurrent = isCurrentManagedAccessYear(historicalYear);
                            const accessPill = historicalIsCurrent ?
                                '<span class="users-access-pill users-access-pill--current"><i class="fa fa-pen-to-square"></i>Tahun berjalan</span>' :
                                (p.historical_access_granted ?
                                    `<span class="users-access-pill users-access-pill--granted"><i class="fa fa-lock-open"></i>Izin tulis ${historicalYear} aktif</span>` :
                                    `<span class="users-access-pill users-access-pill--readonly"><i class="fa fa-lock"></i>Mode lihat saja ${historicalYear}</span>`);

                            let accessAction = '';
                            if (p.can_manage_historical_access && !historicalIsCurrent) {
                                accessAction = p.historical_access_granted ?
                                    `
                                        <button type="button" class="dropdown-item btnRevokeHistoricalYearAccess" data-url="${escapeHtml(p.revoke_historical_access_url || '')}" data-year="${historicalYear}" title="Cabut izin tulis historis">
                                            <i class="fa fa-user-lock"></i>Cabut Izin ${historicalYear}
                                        </button>
                                    ` :
                                    `
                                        <button type="button" class="dropdown-item btnGrantHistoricalYearAccess" data-url="${escapeHtml(p.grant_historical_access_url || '')}" data-year="${historicalYear}" title="Izinkan tulis pada tahun historis">
                                            <i class="fa fa-user-shield"></i>Izinkan Tulis ${historicalYear}
                                        </button>
                                    `;
                            }
                            const historicalAccessState = historicalIsCurrent ? 'Mode tulis standar' :
                                (p.historical_access_granted ? 'Izin tulis aktif' : 'Mode lihat saja');
                            const historicalAccessLabel = escapeHtml(p.historical_access_label || '-');
                            const fileAction = p.file_url ?
                                `<a href="${escapeHtml(p.file_url)}" target="_blank" class="dropdown-item" title="Lihat file SK">
                                    <i class="fa fa-file-lines"></i>Lihat SK
                                </a>` :
                                '';
                            const accessDivider = accessAction ? '<div class="dropdown-divider"></div>' : '';
                            const fileDivider = fileAction ? '<div class="dropdown-divider"></div>' : '';

                            $tbody.append(`
                                <tr class="${p.is_active ? 'users-position-row--active' : ''}">
                                    <td>
                                        <div class="users-position-table-badges">
                                            ${badge}
                                        </div>
                                        ${deactivationSummary}
                                    </td>
                                    <td>
                                        <div class="users-position-table-cell">
                                            <div class="users-position-table-title">${jabatanLabel}</div>
                                            <div class="users-position-table-meta">${unitLabel}</div>
                                            <div class="users-position-table-meta">${instansiLabel}</div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="users-position-table-title">${escapeHtml(p.started_at || '-')}</div>
                                        <div class="users-position-table-meta">Selesai: ${escapeHtml(p.ended_at || 'Masih berjalan')}</div>
                                    </td>
                                    <td>
                                        <div class="users-position-table-title">${documentNumber}</div>
                                        <div class="users-position-table-meta">${documentTypeLabel} - ${documentDate}</div>
                                        <div class="users-position-table-meta">Penerbit: ${issuedBy}</div>
                                        <div class="mt-2">${fileLink}</div>
                                    </td>
                                    <td>
                                        <div class="users-position-table-badges">
                                            ${accessPill}
                                        </div>
                                        <div class="users-position-table-meta">${historicalAccessState}</div>
                                        <div class="users-position-table-meta">${historicalAccessLabel}</div>
                                    </td>
                                    <td class="text-end">
                                        <div class="dropdown users-position-table-actions">
                                            <button class="btn btn-sm btn-outline-secondary users-position-table-action-btn mb-0" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-expanded="false" title="Aksi posisi">
                                                <i class="fa fa-ellipsis-vertical"></i>
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-end users-position-table-menu">
                                                ${btnEdit}
                                                ${btnActivate}
                                                ${btnDeactivate}
                                                ${fileAction}
                                                ${fileDivider}
                                                ${accessAction}
                                                ${accessDivider}
                                                ${btnDelete}
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            `);
                        });
                    })
                    .fail(function() {
                        renderActivePositionError();
                        Swal.fire('Error', 'Gagal memuat posisi.', 'error');
                    });
            }

            $('#formAddPos').on('submit', function(e) {
                e.preventDefault();
                if (window.readOnlyUI?.guardAction()) return;
                const $f = $(this);
                const url = $f.data('action');
                const fd = new FormData($f[0]);

                if (!url) {
                    Swal.fire('Error', 'Endpoint simpan posisi belum siap. Tutup modal lalu buka kembali Kelola Posisi.', 'error');
                    return;
                }

                $('#btnSubmitPosForm').prop('disabled', true);

                $.ajax({
                    url,
                    method: 'POST',
                    data: fd,
                    processData: false,
                    contentType: false
                }).done(function(resp) {
                    modalPositionForm.hide();
                    Swal.fire('Sukses', resp.message || 'Posisi disimpan.', 'success');
                    loadPositionsTable();
                    dt.ajax.reload(null, false);
                    resetPositionFormMode();
                }).fail(function(xhr) {
                    let msg = 'Gagal menyimpan posisi.';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        msg = xhr.responseJSON.message;
                    } else if (xhr.responseJSON?.errors) {
                        const firstFieldErrors = Object.values(xhr.responseJSON.errors)[0];
                        if (Array.isArray(firstFieldErrors) && firstFieldErrors.length) {
                            msg = firstFieldErrors[0];
                        }
                    }
                    Swal.fire('Error', msg, 'error');
                }).always(function() {
                    $('#btnSubmitPosForm').prop('disabled', false);
                });
            });

            $('#positionCards, #activePositionPanel').on('click', '.btnEditPos', function() {
                if (window.readOnlyUI?.guardAction()) return;
                const positionKey = String($(this).data('position-key') || '');
                const position = positionCache[positionKey];
                if (!position?.show_url) return;

                $.get(position.show_url, {
                        tahun: getManagedAccessYear()
                    })
                    .done(function(resp) {
                        if (!resp?.data) {
                            Swal.fire('Error', 'Detail posisi tidak ditemukan.', 'error');
                            return;
                        }

                        positionCache[positionKey] = resp.data;
                        enterEditPositionMode(resp.data);
                        openPositionFormModal();
                    })
                    .fail(function(xhr) {
                        const msg = ajaxErrorMessage(xhr, 'Gagal memuat detail posisi.');
                        Swal.fire('Error', msg, 'error');
                    });
            });

            $('#btnCancelEditPos').on('click', function() {
                resetPositionFormMode();
                modalPositionForm.hide();
            });

            $('#btnAccessYearPrev').on('click', function() {
                applyManagedAccessYear(getManagedAccessYear() - 1);
            });

            $('#btnAccessYearNext').on('click', function() {
                applyManagedAccessYear(getManagedAccessYear() + 1);
            });

            $('#posAccessYear').on('change', function() {
                applyManagedAccessYear($(this).val());
            });

            $('#positionCards').on('click', '.btnActivatePos', function() {
                if (window.readOnlyUI?.guardAction()) return;
                const url = $(this).data('url');
                $.post(url)
                    .done(function(resp) {
                        Swal.fire('Sukses', resp.message || 'Posisi diaktifkan.', 'success');
                        loadPositionsTable();
                        dt.ajax.reload(null, false);
                    })
                    .fail(function() {
                        Swal.fire('Error', 'Gagal mengubah posisi aktif.', 'error');
                    });
            });

            $('#positionCards, #activePositionPanel').on('click', '.btnDeactivatePos', function() {
                if (window.readOnlyUI?.guardAction()) return;
                const positionKey = String($(this).data('position-key') || '');
                const position = positionCache[positionKey];

                if (!position?.deactivate_url) {
                    Swal.fire('Error', 'Endpoint nonaktif posisi tidak ditemukan.', 'error');
                    return;
                }

                openDeactivatePositionModal(position);
            });

            $('#formDeactivatePosition').on('submit', function(e) {
                e.preventDefault();
                if (window.readOnlyUI?.guardAction()) return;
                if (!deactivatePositionAction?.deactivate_url) return;

                const reason = String($('#deactivatePositionReason').val() || '').trim();

                if (!reason) {
                    $('#deactivatePositionReason').trigger('focus');
                    Swal.fire('Alasan wajib diisi', 'Isi alasan administratif sebelum menonaktifkan posisi.', 'warning');
                    return;
                }

                $.post(deactivatePositionAction.deactivate_url, {
                        ended_at: $('#deactivatePositionEndedAt').val(),
                        deactivation_reason: reason,
                    })
                    .done(function(resp) {
                        modalDeactivatePosition.hide();
                        Swal.fire('Sukses', resp.message || 'Posisi berhasil dinonaktifkan.', 'success');
                        loadPositionsTable();
                        dt.ajax.reload(null, false);
                        resetPositionFormMode();
                    })
                    .fail(function(xhr) {
                        let msg = 'Gagal menonaktifkan posisi.';

                        if (xhr.responseJSON?.errors) {
                            const firstFieldErrors = Object.values(xhr.responseJSON.errors)[0];
                            if (Array.isArray(firstFieldErrors) && firstFieldErrors.length) {
                                msg = firstFieldErrors[0];
                            }
                        } else if (xhr.responseJSON?.message) {
                            msg = xhr.responseJSON.message;
                        }

                        Swal.fire('Error', msg, 'error');
                    });
            });

            $('#positionCards').on('click', '.btnDeletePos', function() {
                if (window.readOnlyUI?.guardAction()) return;
                const url = $(this).data('url');
                Swal.fire({
                    title: 'Hapus posisi ini?',
                    text: 'Posisi yang dihapus tidak dapat dikembalikan.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Ya, hapus'
                }).then((res) => {
                    if (res.isConfirmed) {
                        $.post(url, {
                                _method: 'DELETE'
                            })
                            .done(function(resp) {
                                Swal.fire('Terhapus', resp.message || 'Posisi dihapus.', 'success');
                                loadPositionsTable();
                                dt.ajax.reload(null, false);
                            })
                            .fail(function() {
                                Swal.fire('Error', 'Gagal menghapus posisi.', 'error');
                            });
                    }
                });
            });

            $('#positionCards').on('click', '.btnGrantHistoricalYearAccess', function() {
                const url = $(this).data('url');
                const year = $(this).data('year');

                openHistoricalReasonModal('grant', url, year);
            });

            $('#positionCards').on('click', '.btnRevokeHistoricalYearAccess', function() {
                const url = $(this).data('url');
                const year = $(this).data('year');

                openHistoricalReasonModal('revoke', url, year);
            });

            $('#formHistoricalReason').on('submit', function(e) {
                e.preventDefault();
                if (window.readOnlyUI?.guardAction()) return;
                if (!historicalReasonAction?.url) return;

                const reason = String($('#historicalReasonInput').val() || '').trim();
                if (!reason) {
                    $('#historicalReasonInput').trigger('focus');
                    Swal.fire('Alasan wajib diisi', 'Isi alasan administratif sebelum melanjutkan.', 'warning');
                    return;
                }

                const mode = historicalReasonAction.mode;
                const year = historicalReasonAction.year;
                const successFallback = mode === 'grant' ?
                    `Izin tulis tahun ${year} diberikan.` :
                    `Izin tulis tahun ${year} dicabut.`;
                const errorFallback = mode === 'grant' ?
                    'Gagal memberikan izin tulis histori.' :
                    'Gagal mencabut izin tulis histori.';

                const payload = {
                    tahun: year,
                    reason,
                };

                if (mode === 'grant') {
                    payload.reference_number = $('#historicalReferenceNumber').val();
                    payload.reference_date = $('#historicalReferenceDate').val();
                    payload.valid_until = $('#historicalValidUntil').val();
                    payload.grant_notes = $('#historicalGrantNotes').val();
                }

                $.post(historicalReasonAction.url, payload)
                    .done(function(resp) {
                        modalHistoricalReason.hide();
                        Swal.fire('Sukses', resp.message || successFallback, 'success');
                        loadPositionsTable();
                    })
                    .fail(function(xhr) {
                        let msg = errorFallback;
                        if (xhr.responseJSON?.errors) {
                            const firstFieldErrors = Object.values(xhr.responseJSON.errors)[0];
                            if (Array.isArray(firstFieldErrors) && firstFieldErrors.length) {
                                msg = firstFieldErrors[0];
                            }
                        } else if (xhr.responseJSON?.message) {
                            msg = xhr.responseJSON.message;
                        }
                        Swal.fire('Error', msg, 'error');
                    });
            });

            document.getElementById('modalUser').addEventListener('hidden.bs.modal', function() {
                $('#formUser')[0].reset();
                $('#formUser').find('input[name=_method]').remove();
                setUserModalMode('create');
            });

            document.getElementById('modalDeactivatePosition').addEventListener('hidden.bs.modal', function() {
                deactivatePositionAction = null;
                $('#formDeactivatePosition')[0].reset();
                $('#deactivatePositionActionUrl').val('');
            });

            document.getElementById('modalUserSecurity').addEventListener('hidden.bs.modal', function() {
                userSecuritySourceUser = null;
                userSecurityState = null;
                resetUserSecurityModal();
            });

            document.getElementById('modalUserSecurityReason').addEventListener('hidden.bs.modal', function() {
                userSecurityAction = null;
                $('#formUserSecurityReason')[0].reset();
                $('#securityLockedUntilWrap').addClass('d-none');
                $('#securityReasonSubmit')
                    .removeClass('btn-danger btn-success btn-warning')
                    .addClass('btn-primary')
                    .html('<i class="fa fa-floppy-disk me-1"></i>Simpan');
            });

            document.getElementById('modalUserSecurityPasswordResult').addEventListener('hidden.bs.modal', function() {
                $('#securityPasswordResultUserName').text('');
                $('#securityTemporaryPasswordValue').text('-');
                $('#securityTemporaryPasswordCopyStatus')
                    .removeClass('users-temporary-password-status--copied')
                    .html('<i class="fa fa-clock"></i>Belum disalin');
                $('#btnCopyTemporaryPassword')
                    .removeData('temporary-password')
                    .removeClass('btn-success')
                    .addClass('btn-primary');
                $('#btnCopyTemporaryPassword i')
                    .attr('class', 'fa fa-copy');
                $('#btnCopyTemporaryPasswordLabel').text('Salin');
            });

            document.getElementById('modalHistoricalReason').addEventListener('hidden.bs.modal', function() {
                historicalReasonAction = null;
                $('#formHistoricalReason')[0].reset();
                $('#historicalGrantMetadata').addClass('d-none');
                $('#historicalReasonIcon').removeClass('users-historical-icon--revoke');
                $('#historicalReasonIconSymbol').attr('class', 'fa fa-user-shield');
                $('#historicalReasonYearLabel, #historicalReasonActionLabel, #historicalReasonImpactLabel').text('-');
                $('#historicalReasonSubmit')
                    .removeClass('btn-warning')
                    .addClass('btn-primary')
                    .html('<i class="fa fa-floppy-disk me-1"></i>Simpan');
            });

            document.getElementById('modalPositionForm').addEventListener('shown.bs.modal', function() {
                initSelect2('#modalPositionForm');

                if ($('#pos_form_mode').val() === 'edit') {
                    return;
                }

                $('#pos_instansi').empty().append('<option value=""></option>').val(null).trigger('change').prop(
                    'disabled', true);
                $('#pos_unit').empty().append('<option value=""></option>').val(null).trigger('change').prop(
                    'disabled',
                    true);
                $('#pos_instansi_hint, #pos_unit_hint').addClass('d-none');
                $('#pos_instansi, #pos_unit').attr('required', true);
            });

            document.getElementById('modalPositionForm').addEventListener('hidden.bs.modal', function() {
                resetPositionFormMode();

                if (restorePositionsModalAfterForm && currentUser) {
                    restorePositionsModalAfterForm = false;
                    modalPositions.show();
                    return;
                }

                restorePositionsModalAfterForm = false;
            });

            $('#pos_jabatan').on('change', function() {
                const jabatan_id = $(this).val();
                loadPositionInstansiOptions(jabatan_id)
                    .fail(function() {
                        Swal.fire('Error', 'Gagal memuat instansi.', 'error');
                    });
            });

            $('#pos_instansi').on('change', function() {
                const instansi_id = $(this).val();
                const jabatan_id = $('#pos_jabatan').val();
                loadPositionUnitOptions(instansi_id, jabatan_id)
                    .fail(function() {
                        Swal.fire('Error', 'Gagal memuat unit kerja.', 'error');
                    });
            });

            document.getElementById('modalPositions').addEventListener('hidden.bs.modal', function() {
                if (preservePositionsModalState) {
                    return;
                }

                resetPositionFormMode();
                syncManagedAccessYear();
                currentUser = null;
                Object.keys(positionCache).forEach(function(key) {
                    delete positionCache[key];
                });
                $('#posCount').text('0 posisi');
                $('#posActiveLabel').text('Belum ada posisi aktif');
                $('#posYearAccessLabel').text('Tahun aktif belum dibaca');
                $('#posHistoricalAccessHelper').text('');
                renderPositionUserHeader();
                renderActivePositionLoading();
                renderPositionsEmptyState();
            });
        });
    </script>
@endsection
