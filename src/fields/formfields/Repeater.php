<?php
namespace verbb\formie\fields\formfields;

use verbb\formie\base\FormField;
use verbb\formie\base\NestedFieldInterface;
use verbb\formie\base\NestedFieldTrait;
use verbb\formie\elements\Form;
use verbb\formie\elements\db\NestedFieldRowQuery;
use verbb\formie\elements\NestedFieldRow;
use verbb\formie\helpers\SchemaHelper;

use Craft;
use craft\base\EagerLoadingFieldInterface;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\helpers\Html;
use craft\helpers\Json;
use craft\validators\ArrayValidator;
use craft\web\View;

use Throwable;

class Repeater extends FormField implements NestedFieldInterface, EagerLoadingFieldInterface
{
    // Traits
    // =========================================================================

    use NestedFieldTrait {
        getFrontEndJsModules as traitGetFrontEndJsModules;
    }


    // Static Methods
    // =========================================================================

    /**
     * @inheritDoc
     */
    public static function displayName(): string
    {
        return Craft::t('formie', 'Repeater');
    }

    /**
     * @inheritDoc
     */
    public static function getSvgIconPath(): string
    {
        return 'formie/_formfields/repeater/icon.svg';
    }

    /**
     * @inheritdoc
     */
    public static function hasContentColumn(): bool
    {
        return false;
    }


    // Properties
    // =========================================================================

    /**
     * @var int
     */
    public $minRows;

    /**
     * @var int
     */
    public $maxRows;

    /**
     * @var string
     */
    public $addLabel;


    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        $rules = parent::defineRules();
        $rules[] = [['minRows', 'maxRows'], 'integer', 'min' => 0];
        return $rules;
    }

    /**
     * @inheritdoc
     */
    public function getElementValidationRules(): array
    {
        return [
            'validateRows',
            [
                ArrayValidator::class,
                'min' => $this->minRows ?: null,
                'max' => $this->maxRows ?: null,
                'tooFew' => Craft::t('formie', '{attribute} should contain at least {min, number} {min, plural, one{row} other{rows}}.'),
                'tooMany' => Craft::t('formie', '{attribute} should contain at most {max, number} {max, plural, one{row} other{rows}}.'),
                'message' => Craft::t('formie', '{attribute} must have one item.'),
                'skipOnEmpty' => !($this->minRows || $this->maxRows),
                'on' => Element::SCENARIO_LIVE,
            ],
        ];
    }

    /**
     * @inheritDoc
     */
    public function getValueAsString(ElementInterface $element)
    {
        return $this->getValue($element)[0] ?? [];
    }

    /**
     * @inheritDoc
     */
    public function serializeValueForIntegration($value, ElementInterface $element = null)
    {
        $fields = $this->getFields();

        $rows = [];

        foreach ($value->all() as $row) {
            $values = [];

            foreach ($fields as $field) {
                $values[$field->handle] = $field->serializeValue($row->getFieldValue($field->handle), $element);
            }

            $rows[] = $values;
        }

        return $rows;
    }

    /**
     * @inheritDoc
     */
    public function getIsFieldset(): bool
    {
        return true;
    }

    /**
     * @return array
     */
    public function getFieldDefaults(): array
    {
        return [
            'addLabel' => Craft::t('formie', 'Add another row'),
        ];
    }

    /**
     * @inheritDoc
     */
    public function getInputHtml($value, ElementInterface $element = null): string
    {
        return Craft::$app->getView()->renderTemplate('formie/_formfields/repeater/input', [
            'name' => $this->handle,
            'value' => $value,
            'field' => $this,
        ]);
    }

    /**
     * @inheritDoc
     */
    public function getPreviewInputHtml(): string
    {
        return Craft::$app->getView()->renderTemplate('formie/_formfields/repeater/preview', [
            'field' => $this
        ]);
    }

    /**
     * @inheritdoc
     */
    public function getFrontEndJsModules()
    {
        $modules = [];
        
        $modules[] = [
            'src' => Craft::$app->getAssetManager()->getPublishedUrl('@verbb/formie/web/assets/frontend/dist/js/fields/repeater.js', true),
            'module' => 'FormieRepeater',
        ];

        // Ensure we also load any JS in nested fields
        $modules = array_merge($modules, $this->traitGetFrontEndJsModules());

        return $modules;
    }

    /**
     * @inheritDoc
     */
    public function getConfigJson()
    {
        // Override `getConfigJson` as we don't want to initialise any inner fields immediately.
        // Even if there are min-rows, JS is the one to create the blocks, and initialize inner field JS.
        return Json::encode([
            'module' => 'FormieRepeater',
        ]);
    }

    /**
     * @inheritDoc
     */
    public function defineGeneralSchema(): array
    {
        return [
            SchemaHelper::labelField(),
            SchemaHelper::textField([
                'label' => Craft::t('formie', 'Add Label'),
                'help' => Craft::t('formie', 'The label for the button that adds another instance.'),
                'name' => 'addLabel',
                'validation' => 'required',
                'required' => true,
            ]),
        ];
    }

    /**
     * @inheritDoc
     */
    public function defineSettingsSchema(): array
    {
        return [
            SchemaHelper::textField([
                'label' => Craft::t('formie', 'Minimum instances'),
                'help' => Craft::t('formie', 'The minimum required number of instances of this repeater‘s fields that must be completed.'),
                'type' => 'number',
                'name' => 'minRows',
                'validation' => 'optional|number|min:0',
            ]),
            SchemaHelper::textField([
                'label' => Craft::t('formie', 'Maximum instances'),
                'help' => Craft::t('formie', 'The maximum required number of instances of this repeater‘s fields that must be completed.'),
                'type' => 'number',
                'name' => 'maxRows',
                'validation' => 'optional|number|min:0',
            ]),
        ];
    }

    /**
     * @inheritDoc
     */
    public function defineAppearanceSchema(): array
    {
        return [
            SchemaHelper::labelPosition($this),
            SchemaHelper::instructions(),
            SchemaHelper::instructionsPosition($this),
        ];
    }

    /**
     * @inheritDoc
     */
    public function defineAdvancedSchema(): array
    {
        return [
            SchemaHelper::handleField(),
            SchemaHelper::cssClasses(),
            SchemaHelper::containerAttributesField(),
        ];
    }
}
