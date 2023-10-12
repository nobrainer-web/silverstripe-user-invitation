<?php

namespace FSWebWorks\SilverStripe\UserInvitations\Model;

use LeKoala\CmsActions\CustomAction;
use SilverStripe\Control\Director;
use SilverStripe\Control\Email\Email;
use SilverStripe\Forms\CheckboxSetField;
use SilverStripe\Forms\ListboxField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\RequiredFields;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\Security\Group;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\RandomGenerator;

/**
 * Class UserInvitation
 * @package FSWebWorks
 * @subpackage UserInvitation
 *
 * @property string FirstName
 * @property string Email
 * @property string TempHash
 * @property string Groups
 * @property int    InvitedByID
 * @property Member InvitedBy
 *
 */
class UserInvitation extends DataObject
{
    private static $table_name = 'UserInvitation';

    /**
     * Used to control whether a group selection on the invitation form is required.
     * @var bool
     */
    private static $force_require_group = false;

    private static $db = [
        'FirstName' => 'Varchar',
        'Email'     => 'Varchar(254)',
        'TempHash'  => 'Varchar',
        'Groups'    => 'Text'
    ];

    private static $has_one = [
        'InvitedBy' => Member::class
    ];

    private static $indexes = [
        'Email'    => true,
        'TempHash' => true
    ];

    private static $summary_fields = [
        'FirstName',
        'Email',
    ];

    public function provideI18nEntities()
    {
        return [
            'UserInvitation.SINGULARNAME' => 'User invitation',
            'UserInvitation.PLURALNAME' => 'User invitations',
            'UserInvitation.PLURALS' => [
                'one' => 'An User invitation',
                'other' => '{count} User invitations',
            ],
        ];
    }

    /**
     * Removes the hash field from the list.
     * @return FieldList
     */
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        //$fields->removeByName(['TempHash']);

        $groups = Group::get()->map('Code', 'Title')->toArray();

        $fields->addFieldsToTab('Root.Main', [
            CheckboxSetField::create(
                'Groups',
                _t('UserController.INVITE_GROUP', 'Add to group'),
                $groups
            )
        ]);

        $fields->replaceField('TempHash',
            $fields->dataFieldByName('TempHash')->performReadonlyTransformation());
        $fields->replaceField('InvitedByID',
            $fields->dataFieldByName('InvitedByID')->performReadonlyTransformation());

        return $fields;
    }

    public function onBeforeWrite()
    {
//        $groups_raw = str_replace(['[',']', '"'], '', $this->Groups);
//
//        echo "<pre>";
//        echo "Groups: " . $this->dbObject('Groups') . "<br>";
//        echo "Groups replaced: " . $groups_raw . "<br>";
//        $groups = explode(',', $groups_raw);
//        // TODO: Fix this
//        foreach ($groups as $groupCode) {
//            echo "Group code: " . $groupCode . "<br>";
//        }
//
//        echo "</pre>";
//        die;
//
        if (!$this->ID) {
            $generator = new RandomGenerator();
            $this->TempHash = $generator->randomToken('sha1');
            $this->InvitedByID = Member::currentUserID();
        }
        parent::onBeforeWrite();
    }

    /**
     * Sends an invitation to the desired user
     */
    public function sendInvitation()
    {
        $email = Email::create()
            ->setFrom(Email::config()->get('admin_email'))
            ->setTo($this->Email)
            ->setSubject(
                _t(
                    'UserInvation.EMAIL_SUBJECT',
                    'Invitation from {name}',
                    ['name' => $this->InvitedBy()->FirstName]
                )
            )->setHTMLTemplate('email/UserInvitationEmail')
            ->setData(
                [
                    'Invite'  => $this,
                    'SiteURL' => Director::absoluteBaseURL(),
                ]
            );

        $email->send();

        return $email;
    }

    public function getCMSValidator()
    {
        return new RequiredFields([
            'FirstName',
            'Email'
        ]);
    }

    /**
     * Checks if a user invite was already sent, or if a user is already a member
     * @return ValidationResult
     */
    public function validate()
    {
        $validator = parent::validate();
                $exists = $this->isInDB();

                        if (!$exists) {
                            // We are creating a new invite
        if (self::get()->filter('Email', $this->Email)->first()) {
            // UserInvitation already sent
            $validator->addError(_t('UserInvitation.INVITE_ALREADY_CREATED', 'An invite was already created for this user.'));
        }

        if (Member::get()->filter('Email', $this->Email)->first()) {
            // Member already exists
            $validator->addError(_t(
                'UserInvitation.MEMBER_ALREADY_EXISTS',
                'This person is already a member of this system.'
            ));
        }
                        }

        return $validator;
    }

    /**
     * Checks if this invitation has expired
     * @return bool
     */
    public function isExpired()
    {
        $result = false;
        $days = self::config()->get('days_to_expiry');
        $time = DBDatetime::now()->getTimestamp();
        $ago = abs($time - strtotime($this->LastEdited));
        $rounded = round($ago / 86400);
        if ($rounded > $days) {
            $result = true;
        }

        return $result;
    }

    public function canCreate($member = null, $context = null)
    {
        return Permission::check('ACCESS_USER_INVITATIONS');
    }

    public function getCMSActions()
    {
        $actions = parent::getCMSActions();

        if ($this->isInDB()) {
        $actions->push(new CustomAction("doCustomActionSendInvitation", "Send invitation"));
        } else {
            $actions->push(LiteralField::create('doCustomActionSendInvitationUnavailable', "<span class=\"bb-align\">" . _t('UserInvitation.CreateSaveBeforeSending', 'Create/Save before sending invite!')."</span>"));
        }

        return $actions;
    }

    public function doCustomActionSendInvitation() {

        if ($email = $this->sendInvitation()) {
            return $email;
        }

        return 'Invite was NOT send';
    }
}
