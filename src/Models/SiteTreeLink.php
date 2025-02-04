<?php declare(strict_types=1);

namespace SilverStripe\LinkField\Models;

use SilverStripe\CMS\Forms\AnchorSelectorField;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Controller;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\TreeDropdownField;

/**
 * A link to a Page in the CMS
 *
 * @property int $PageID
 * @property string $Anchor
 * @property string $QueryString
 * @method SiteTree Page()
 */
class SiteTreeLink extends Link
{
    private static string $table_name = 'LinkField_SiteTreeLink';

    private static array $db = [
        'Anchor' => 'Varchar',
        'QueryString' => 'Varchar',
    ];

    private static array $has_one = [
        'Page' => SiteTree::class,
    ];

    public function generateLinkDescription(array $data): string
    {
        $pageId = $data['PageID'] ?? null;

        if (!$pageId) {
            return '';
        }

        /** @var SiteTree $page */
        $page = SiteTree::get()->byID($pageId);

        if (!$page?->exists()) {
            return '';
        }

        return $page->URLSegment ?: '';
    }

    public function getCMSFields(): FieldList
    {
        $self = $this;
        $this->beforeUpdateCMSFields(static function (FieldList $fields) use (
            $self
        ) {
            // Remove scaffolded fields to we don't have field name conflicts which would prevent field customisation
            $fields->removeByName(['PageID', 'Anchor', 'QueryString']);

            $titleField = $fields->dataFieldByName('Title');
            $titleField?->setDescription(
                _t(
                    __CLASS__ . '.Title_Description',
                    'Auto generated from Page title if left blank'
                )
            );

            $fields->insertAfter(
                'Title',
                TreeDropdownField::create(
                    'PageID',
                    $self->fieldLabel('Page'),
                    SiteTree::class,
                    'ID',
                    'TreeTitle'
                )
            );

            $fields->insertAfter(
                'PageID',
                $queryStringField = TextField::create('QueryString')
            );

            $queryStringField->setDescription(
                _t(
                    __CLASS__ . '.QueryString_Description',
                    'Do not prepend "?". EG: "option1=value&option2=value2"'
                )
            );

            $fields->insertAfter(
                'QueryString',
                $anchorField = AnchorSelectorField::create(
                    'Anchor',
                    $self->fieldLabel('Anchor')
                )
            );

            $anchorField->setDescription(
                _t(
                    __CLASS__ . '.Anchor_Description',
                    'Do not prepend "#". Anchor suggestions will be displayed once the linked page is attached.'
                )
            );
        });

        return parent::getCMSFields();
    }

    public function getURL(): string
    {
        $page = $this->Page();
        $url = $page->exists() ? $page->Link() : '';
        $anchorSegment = $this->Anchor ? '#' . $this->Anchor : '';
        $queryStringSegment = $this->QueryString
            ? '?' . $this->QueryString
            : '';

        $this->extend('updateGetURLBeforeAnchor', $url);

        return Controller::join_links(
            $url,
            $anchorSegment,
            $queryStringSegment
        );
    }

    /**
     * Try to populate link title from page title in case we don't have a title yet
     *
     * @return string|null
     */
    public function getTitle(): ?string
    {
        $title = $this->getField('Title');

        if ($title) {
            // If we already have a title, we can just bail out without any changes
            return $title;
        }

        $page = $this->Page();

        if (!$page?->exists()) {
            // We don't have a page to fall back to
            return null;
        }

        // Use page title as a default value in case CMS user didn't provide the title
        return $page->Title;
    }

    public function fieldLabels($includerelations = true)
    {
        return array_merge(parent::fieldLabels($includerelations), [
            'Anchor' => _t(__CLASS__ . '.Anchor', 'Anchor'),
            'QueryString' => _t(__CLASS__ . '.QueryString', 'Query string'),
            'Page' => _t(__CLASS__ . '.Page', 'Page'),
        ]);
    }
}
