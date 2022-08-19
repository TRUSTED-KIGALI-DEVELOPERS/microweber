<?php

namespace MicroweberPackages\Product\Models;

use MicroweberPackages\Content\Scopes\ProductScope;
use MicroweberPackages\Content\Content;
use MicroweberPackages\CustomField\Models\CustomField;
use MicroweberPackages\CustomField\Models\CustomFieldValue;
use MicroweberPackages\Product\CartesianProduct;
use MicroweberPackages\Product\Models\ModelFilters\ProductFilter;
use MicroweberPackages\Product\Traits\CustomFieldPriceTrait;
use MicroweberPackages\Shop\FrontendFilter\ShopFilter;

class Product extends Content
{

    /**
     * @method filter(array $filter)
     * @see ProductFilter
     */

    use CustomFieldPriceTrait;

    protected $table = 'content';

    protected $appends = ['price', 'qty', 'sku'];

    //  public $timestamps = false;

    public $fillable = [
        "subtype",
        "subtype_value",
        "content_type",
        "parent",
        "layout_file",
        "active_site_template",
        "title",
        "url",
        "content_meta_title",
        "content",
        "description",
        "content_body",
        "content_meta_keywords",
        "original_link",
        "require_login",
        "created_by",
        "is_home",
        "is_shop",
        "is_active",
        "updated_at",
        "created_at",
    ];


    public static $customFields = [
        [
            'type' => 'price',
            'name' => 'Price',
            'value' => [0]
        ]
    ];

    public static $contentDataDefault = [
        'qty' => 'nolimit',
        'sku' => '',
        'barcode' => '',
        'track_quantity' => '',
        'max_quantity_per_order' => '',
        'sell_oos' => '',
        'physical_product' => '',
        'free_shipping' => '',
        'shipping_fixed_cost' => '',
        'weight_type' => 'kg',
        'params_in_checkout' => 0,
        'has_special_price' => 0,
        'weight' => '',
        'width' => '',
        'height' => '',
        'depth' => ''
    ];

    public $sortable = [
        'id' => [
            'title' => 'Product'
        ]
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->attributes['content_type'] = 'product';
        $this->attributes['subtype'] = 'product';
    }


    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {
        static::addGlobalScope(new ProductScope());
    }


    public function modelFilter()
    {
        return $this->provideFilter(ProductFilter::class);
    }

    private function fetchSingleAttributeByType($type, $returnAsObject = false)
    {
        foreach ($this->customField as $customFieldRow) {
            if ($customFieldRow->type == $type) {
                if (isset($customFieldRow->fieldValue[0]->value)) { //the value field must be only one
                    if ($returnAsObject) {
                        return $customFieldRow;
                    }
                    return $customFieldRow->fieldValue[0]->value;
                }
            }
        }

        return null;
    }

    private function fetchSingleAttributeByName($name, $returnAsObject = false)
    {
        foreach ($this->customField as $customFieldRow) {
            if ($customFieldRow->name_key == $name) {
                if (isset($customFieldRow->fieldValue[0]->value)) { //the value field must be only one
                    if ($returnAsObject) {
                        return $customFieldRow;
                    }
                    return $customFieldRow->fieldValue[0]->value;
                }
            }
        }

        return null;
    }

    public function getPriceAttribute()
    {
        return $this->fetchSingleAttributeByType('price');
    }

    public function getPriceModelAttribute()
    {
        return $this->fetchSingleAttributeByType('price', true);
    }

    public function getQtyAttribute()
    {
        return $this->getContentDataByFieldName('qty');
    }

    public function getSkuAttribute()
    {
        return $this->getContentDataByFieldName('sku');
    }

    public function hasSpecialPrice()
    {
        $specialPrice = $this->getContentDataByFieldName('special_price');
        if ($specialPrice > 0) {
            return true;
        }
        return false;
    }

    public function getSpecialPriceAttribute()
    {
        return $this->getContentDataByFieldName('special_price');
    }

    public function getInStockAttribute()
    {

        $sellWhenIsOos = $this->getContentDataByFieldName('sell_oos');
        if ($sellWhenIsOos == '1') {
            return true;
        }

        if ($this->qty == 'nolimit') {
            return true;
        }

        if ($this->qty > 0) {
            return true;
        }

        return false;
    }

    public function variants()
    {
        return $this->hasMany(ProductVariant::class, 'parent');
    }

    public function generateVariants()
    {
        $getVariants = $this->variants()->get();
        $getCustomFields = $this->customField()->where('type', 'radio')->get();

        // Get all custom fields for variations
        $productCustomFieldsMap = [];
        foreach ($getCustomFields as $customField) {

            $customFieldValues = [];
            $getCustomFieldValues = $customField->fieldValue()->get();
            foreach ($getCustomFieldValues as $getCustomFieldValue) {
                $customFieldValues[] = $getCustomFieldValue->id;
            }
            $productCustomFieldsMap[$customField->id] = $customFieldValues;
        }

        // Make combinations ofr custom fields
        $cartesianProduct = new CartesianProduct($productCustomFieldsMap);
        $cartesianProduct = $cartesianProduct->asArray();

        // Match old variants with new cartesian variants
        $matchWithCartesian = [];
        if ($getVariants->count() > 0) {
            foreach ($getVariants as $variant) {
                $contentData = $variant->getContentData();
                if (!empty($contentData)) {
                    foreach ($contentData as $contentDataKey => $contentDataValue) {
                        foreach ($cartesianProduct as $cartesianProductCustomFields) {
                            foreach ($cartesianProductCustomFields as $customFieldId => $customFieldValueId) {
                                if ($contentDataKey == 'variant_cfi_' . $customFieldId && $contentDataValue == $customFieldValueId) {
                                    $matchWithCartesian[$variant->id]['cfi_'.$customFieldId .'_cfvi_'. $customFieldValueId] = [
                                        'variant_id' => $variant->id,
                                        'content_data_key' => $contentDataKey,
                                        'custom_field_id' => $customFieldId,
                                        'custom_field_value_id' => $customFieldValueId,
                                        'cartesian_content_data' => $cartesianProductCustomFields,
                                    ];
                                }
                            }
                        }
                    }
                }
            }
        }
        
        // Update existing variants matched with new cartesian
        if (!empty($matchWithCartesian)) {
            foreach ($matchWithCartesian as $matchCartesian) {
                $matchCartesian = end($matchCartesian);
                $matchedVariantContentData = [];
                foreach ($matchCartesian['cartesian_content_data'] as $customFieldId => $customFieldValueId) {
                    $matchedVariantContentData['variant_cfi_' . $customFieldId] = $customFieldValueId;
                }
                $getMatchedVariant = ProductVariant::where('parent', $this->id)->where('id', $matchCartesian['variant_id'])->first();
                $getMatchedVariant->setContentData($matchedVariantContentData);
                $getMatchedVariant->save();
            }
        }

        $updatedProductVariantIds = [];
        foreach ($cartesianProduct as $cartesianProductCustomFields) {

            $productVariantContentData = [];
            $cartesianProductVariantValues = [];
            foreach ($cartesianProductCustomFields as $customFieldId => $customFieldValueId) {
                $getCustomFieldValue = CustomFieldValue::where('id', $customFieldValueId)->first();
                $cartesianProductVariantValues[] = $getCustomFieldValue->value;
                $productVariantContentData['variant_cfi_' . $customFieldId] = $customFieldValueId;
            }

            $productVariant = ProductVariant::where('parent', $this->id)->whereContentData($productVariantContentData)->first();

            if ($productVariant == null) {
                $productVariant = new ProductVariant();
                $productVariant->parent = $this->id;
            }

            $productVariantUrl = $this->url . '-' . str_slug(implode('-', $cartesianProductVariantValues));
            $productVariant->title = 'id->' . $productVariant->id . '-' . $this->title . ' - ' . implode(', ', $cartesianProductVariantValues);
            $productVariant->url = $productVariantUrl;
            $productVariant->setContentData($productVariantContentData);
            $productVariant->save();

            $updatedProductVariantIds[] = $productVariant->id;
        }

        // Delete old variants
        if ($getVariants->count() > 0) {
            foreach ($getVariants as $productVariant) {
                if (!in_array($productVariant->id, $updatedProductVariantIds)) {
                    $productVariant->delete();
                }
            }
        }
    }

    public function getContentData($values = [])
    {
        $defaultKeys = self::$contentDataDefault;
        $contentData = parent::getContentData($values);

        foreach ($contentData as $key => $value) {
            $defaultKeys[$key] = $value;
        }

        return $defaultKeys;
    }

    public function scopeFrontendFilter($query, $params)
    {
        $filter = new ShopFilter();
        $filter->setModel($this);
        $filter->setQuery($query);
        $filter->setParams($params);

        return $filter->apply();
    }
}
