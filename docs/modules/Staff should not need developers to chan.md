Staff should not need developers to change a price, billing cycle, approval requirement, grace period, property limit, invoice rule, suspension behaviour, or notification period.

Property Platform — Dynamic Administration & Billing Configuration Specification
1. Core Design Principle

The system should be configuration-driven, not code-driven.

For example, an administrator changes:

Professional Plan
From $30/month → $35/month

The system should automatically use $35 for:

New subscriptions
Renewal invoices
Upgrade calculations
Reports
Statements
Receipts
Payment verification
Revenue reports

There should be one source of truth for every commercial rule.

2. Global Configuration Centre

Create:

⚙️ System Configuration
General
Platform name
Platform logo
Platform email
Support email
Support phone
Physical address
Currency
Default timezone
Date format
Number format
Financial year
Default language
System maintenance mode
Registration enabled/disabled
Tenant registration enabled/disabled
Landlord registration enabled/disabled
3. Registration Configuration

Administrators control how registration works.

Tenant Registration
Registration enabled
Email verification required
Phone verification required
Admin approval required
Automatically activate after verification
Require profile completion
Require terms acceptance
Landlord Registration
Registration enabled
Email verification
Phone verification
Admin approval required
Documents required
Proof of ownership required
Proof of payment required for premium access
Manual admission required
Automatic admission allowed/disallowed

Example:

Landlord Registration

Registration: ENABLED
Admin Approval: REQUIRED
Document Verification: REQUIRED
Premium Access: ADMIN APPROVAL REQUIRED
4. User Admission Workflow

Make admission configurable.

Possible statuses
Registered
Pending Verification
Documents Required
Documents Submitted
Under Review
Approved
Rejected
Suspended
Deactivated

Admin should be able to configure which transitions are allowed.

5. User Type Configuration

Don't hard-code user types.

Admin can manage:

Tenant
Landlord
Property Manager
Agent
Staff
Administrator
Corporate Landlord
Developer
Service Provider

And configure what each type can access.

6. Role & Permission Management

A proper RBAC system.

Admin can create:

Finance Officer

and give access to:

Invoices
Payments
Receipts
Statements

but not:

User deletion
System configuration
Subscription pricing

Another:

Verification Officer

gets:

Owner verification
Property verification
Document review

but cannot change prices.

7. Subscription Plan Configuration

This should be completely dynamic.

Admin can create:

Plan
Plan name
Description
Code
User type
Price
Currency
Billing frequency
Billing interval
Trial period
Grace period
Property allowance
User allowance
Feature access
Status
Start date
End date

Billing frequency:

One-time
Daily
Weekly
Monthly
Quarterly
Semi-annually
Annually
Custom
8. Subscription Feature Configuration

Create a central Feature Catalogue.

For example:

PROPERTY_LISTING
PROPERTY_ANALYTICS
ADVANCED_SEARCH
TENANT_MESSAGING
VIEWING_MANAGEMENT
APPLICATION_MANAGEMENT
RENT_COLLECTION
MAINTENANCE
FINANCIAL_REPORTS
FEATURED_LISTINGS
MULTIPLE_USERS
MULTIPLE_BRANCHES
API_ACCESS

Admin decides which plans have which features.

9. Property Limits

Very important.

Admin can configure:

Starter
Maximum Active Properties: 3
Professional
Maximum Active Properties: 15
Business
Maximum Active Properties: 50
Enterprise
Maximum Active Properties: Unlimited

The system must enforce this automatically.

If a landlord has reached their limit:

You have reached your property limit. Upgrade your subscription or purchase an additional property slot.

10. Additional Property Configuration

Admin controls:

Price per additional property
Billing frequency
Whether additional properties renew automatically
Whether they count toward plan limits
Maximum additional properties
Proration rules
Upgrade behaviour
11. Proration Settings

This is important once you have upgrades/downgrades.

Suppose:

Starter = $20
Professional = $50

A landlord upgrades halfway through the month.

Admin configures whether the system:

Option 1

Charge the difference immediately.

Option 2

Credit unused portion and generate new invoice.

Option 3

Apply upgrade at next renewal.

The system follows the selected configuration.

12. Renewal Settings

Admin controls:

Auto-renewal
Renewal invoice generation
Renewal notice period
Renewal payment deadline
Grace period
Suspension date
Renewal reminders

Example:

30 days before expiry → reminder
14 days → reminder
7 days → reminder
3 days → reminder
1 day → reminder
Expiry → subscription expired
7 days later → suspend

All configurable.

13. Suspension Configuration

Admin controls exactly what happens.

When subscription expires:

Option A

Hide listings.

Option B

Keep existing listings visible but prevent new listings.

Option C

Suspend all premium features.

Option D

Full account suspension.

These behaviours should be configurable.

14. Grace Period Configuration

Admin can set:

Grace Period:
7 days

or:

Grace Period:
14 days

And separately configure:

During grace period
Can login: Yes
Can edit properties: Yes
Can create properties: No
Can receive enquiries: Yes
Listings visible: Yes
Premium features: No

Again, the system automatically enforces it.

15. Payment Configuration

Admin controls every supported payment method.

Example:

Bank Transfer
Enabled
Bank name
Account name
Account number
Branch
Payment instructions
Reference format
Mobile Money
Enabled
Provider
Merchant details
Instructions
Online Payment
Enabled
Provider
Currency
Callback behaviour
Manual Payment
Enabled
POP required
Admin approval required
16. Proof of Payment Configuration

This should be dynamic too.

Admin controls:

POP required
Allowed file types
Maximum file size
Required payment reference
Required payment date
Required amount
Manual approval
Auto-verification if supported
POP expiry
Duplicate POP detection
17. Payment Approval Rules

Example:

Payment < $100
→ One staff approval

Payment ≥ $100
→ Finance approval

Payment ≥ $1,000
→ Senior administrator approval

You can make approval thresholds configurable.

This is extremely useful as the business grows.

18. Invoice Configuration

Admin controls:

Invoice prefix
Invoice numbering
Invoice starting number
Invoice date
Due date
Payment terms
Default payment terms
Tax/VAT behaviour
Discounts
Surcharges
Notes
Terms & conditions
Footer
Logo
Company details
19. Automatic Invoice Rules

The system can automatically generate invoices for:

New subscription
Renewal
Additional property
Featured listing
Verification
Premium tenant
Property management
Other configurable services

Admin decides which services automatically generate invoices.

20. Invoice Timing

For every billable service:

Generate invoice immediately
Generate invoice X days before
Generate on billing date
Generate after approval

Example:

Renewal invoice: 7 days before expiry.

The system automatically generates it.

21. Receipt Configuration

Admin controls:

Receipt prefix
Number sequence
Receipt format
Automatic generation
Email receipt
Download receipt
Print receipt
Payment information
Footer
Terms
22. Statement Configuration

Configure:

Statement frequency
Monthly statements
On-demand statements
Opening balance
Closing balance
Outstanding invoices
Payments
Credits
Refunds
Adjustments
23. Late Payment Configuration

Admin can configure:

Late payment fee
Fixed amount
Percentage
Daily
Weekly
Monthly
One-time

Example:

Late Fee:
5%

Maximum:
$20

The billing engine automatically calculates it.

24. Discounts

Dynamic discount engine.

Admin can create:

Discount
Fixed amount
Percentage
Subscription-specific
Property-specific
Customer-specific
First subscription
Renewal
Promotional
Bulk
25. Promo Codes

Admin controls:

Code
Discount
Start date
Expiry
Maximum uses
Per-user limit
Eligible plans
Minimum amount
New customers only
Existing customers
Active/inactive
26. Refund Configuration

Admin defines:

Refund allowed
Refund approval required
Full refund
Partial refund
Refund window
Cancellation fee
Processing fee
Refund method
27. Credit System

This would be very useful.

Instead of always refunding money:

Customer Credit: $15

The $15 can automatically be applied against future invoices.

Admin controls:

Credit expiry
Credit usage
Manual credit
Promotional credit
Refund credit
28. Featured Listing Configuration

Admin controls:

Price
Duration
Maximum featured properties
Placement
Priority
Auto-expiry
Renewal
Featured categories
Featured locations

Example:

Homepage Featured
$10
7 days

Search Featured
$5
7 days
29. Verification Configuration
Owner Verification

Admin controls:

Verification required
Documents required
Approval required
Fee
Expiry
Reverification period
Property Verification

Same concept.

30. Property Listing Configuration

Admin controls:

Maximum photos
Maximum video
Listing duration
Listing renewal
Required fields
Minimum property information
Location requirements
Contact requirements
Moderation requirements
Approval requirements
31. Listing Approval Rules

You can have:

Landlord submits property
        ↓
Automatic validation
        ↓
Admin review
        ↓
Approved
        ↓
Published

Or:

Verified landlord
        ↓
Automatic publication

Admin chooses the policy.

32. Tenant Configuration

Admin controls:

Free account
Premium account
Enquiry limits
Viewing limits
Application limits
Saved searches
Notifications
Premium features
Subscription price
Billing period
33. Application Configuration

Admin controls:

Application fee
Application validity
Required documents
Maximum applications
Application expiry
Owner approval
Admin approval
Withdrawal rules
34. Viewing Configuration

Admin controls:

Viewing fee
Maximum viewing requests
Cancellation window
Rescheduling rules
Viewing confirmation
Reminder timing
No-show rules
35. Notification Configuration

This should be extremely dynamic.

Admin should configure:

Events
Registration
Approval
Rejection
Subscription
Invoice
Payment
Receipt
Expiry
Suspension
Property approval
Viewing
Application
Maintenance
Channels
In-app
Email
SMS
WhatsApp
Timing
7 days before
3 days before
1 day before
Immediately
36. Notification Templates

Don't hard-code messages.

Admin can edit:

Subscription Expiry Email

Hello {{customer_name}}, your {{plan_name}} subscription expires on {{expiry_date}}.

Variables can include:

{{customer_name}}
{{property_name}}
{{invoice_number}}
{{amount}}
{{due_date}}
{{subscription_name}}
{{expiry_date}}
{{payment_reference}}
37. Tax Configuration

If the platform later becomes VAT-registered or expands into different jurisdictions, you don't want to rebuild billing.

Support:

Tax enabled/disabled
Tax name
Tax percentage
Tax-inclusive pricing
Tax-exclusive pricing
Tax exemptions
Tax by service
Tax by customer type
38. Currency Configuration

Support:

Base currency
Supported currencies
Exchange rates
Manual exchange rates
Automatic rates if integrated
Currency rounding
Decimal places
39. Numbering Configuration

Every financial document gets its own configurable sequence.

Invoice: INV-000001
Receipt: RCPT-000001
Credit Note: CN-000001
Payment: PAY-000001
Statement: STM-000001
Subscription: SUB-000001

Admin can configure:

Prefix
Starting number
Padding
Financial-year reset
Branch-specific numbering
40. Branch Configuration

If the business expands:

Harare
Bulawayo
Mutare
Gweru

Each branch can have:

Staff
Properties
Customers
Billing
Revenue
Number sequences
Approval rules
41. Approval Engine

This should be a general system, not something built separately into every module.

For example:

SUBSCRIPTION APPROVAL
PAYMENT APPROVAL
OWNER APPROVAL
PROPERTY APPROVAL
REFUND APPROVAL
VERIFICATION APPROVAL

Admin configures:

Who approves what?

How many approvals?

What amount requires escalation?

42. Approval Levels

Example:

Level 1
Verification Officer

Level 2
Finance Officer

Level 3
Administrator

The system routes records automatically.

43. Automatic Business Rules Engine

This is the part I'd especially recommend.

Create a general:

Business Rules Engine

Rules such as:

IF subscription expires
THEN start grace period
IF grace period ends
THEN suspend premium features
IF payment is approved
THEN mark invoice paid
AND generate receipt
AND activate subscription
IF landlord reaches property limit
THEN prevent additional active listings
IF featured listing expires
THEN remove featured status
IF invoice becomes overdue
THEN mark overdue
AND send notification

These shouldn't require staff to manually perform every step.

44. Critical Principle: Configuration Must Drive Behaviour

For example, admin sets:

Professional Plan
Price = $40
Period = Monthly
Grace Period = 10 days
Maximum Properties = 20

The system must use those values everywhere.

If the admin changes:

Price = $45
Maximum Properties = 25
Grace Period = 14 days

the billing/subscription engine should automatically use the new configuration for applicable new billing cycles, while preserving historical invoice/subscription data.

That's important.

Never change old invoices because a setting changed.

Old invoice:

$40

must remain $40.

New invoice:

$45

uses the new price.

45. Configuration Versioning

This is an advanced feature I'd absolutely include.

When an administrator changes:

Professional = $40 → $45

record:

Setting
Old Value
New Value
Changed By
Changed At
Reason

And ideally maintain effective dates:

$40
Effective: 01 Jan – 30 Sep

$45
Effective: 01 Oct onward

This makes financial reporting and audits much safer.

46. Configuration Change Approval

For sensitive settings, don't let one admin instantly change them.

For example:

Low-risk

Change notification template.

→ Immediate.

Medium-risk

Change property listing limit.

→ Optional approval.

High-risk

Change subscription price.

→ Require administrator approval.

Critical

Change payment/bank details.

→ Require senior approval.

Again, configurable.

47. Admin Dashboard

The administrator should be able to see:

Pending Actions
23 Owner Registrations
17 Property Verifications
8 POPs
12 Payment Approvals
4 Refund Requests
6 Subscription Activations

And:

Financial
Today's Revenue       $1,250
Monthly Revenue      $32,450
Outstanding           $6,240
Overdue               $2,130
Active Subscriptions    843

And:

Platform
Landlords             1,240
Tenants               8,450
Properties            4,320
Active Listings       1,180
48. The Final Architecture

I would structure this subsystem roughly like:

                    ADMIN CONFIGURATION
                           │
                           ▼
                  CONFIGURATION ENGINE
                           │
          ┌────────────────┼────────────────┐
          ▼                ▼                ▼
    BILLING ENGINE   APPROVAL ENGINE   RULE ENGINE
          │                │                │
          ▼                ▼                ▼
   SUBSCRIPTIONS      APPROVALS       AUTOMATIONS
          │
          ├──────────────┐
          ▼              ▼
       INVOICES       PAYMENTS
          │              │
          ▼              ▼
       RECEIPTS       VERIFICATION
          │
          ▼
      STATEMENTS
          │
          ▼
       REPORTING

And the most important rule is:

No financial or premium behaviour should be hard-coded when it can reasonably be represented as configuration.

The admin changes the configuration → the system validates it → the configuration becomes effective → the billing engine uses it → invoices/receipts/statements reflect it → subscription entitlements change → access control enforces it → notifications are triggered → everything is audited.

That will give you a genuinely dynamic ERP, rather than an application where the admin panel merely edits records while the actual business rules remain buried in Laravel code.

