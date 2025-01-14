import React, { Fragment } from "react";
import {
    Card,
    TextField,
    Icon,
    Button,
    Checkbox,
    InlineError
} from "@shopify/polaris";
import SeeMoreButton from ".././../UI/SeeMoreButton";
import { DeleteMajorMonotone, CancelSmallMinor } from "@shopify/polaris-icons";
import SelectField from "../../UI/SelectField";
import InputField from "../../UI/InputField";

const Form = ({
    el,
    idx,
    handleInputChange,
    handleSelectChange,
    handleSelect,
    removeSelectedCustomer,
    removeSelectedAppliedIds,
    removeSelectedCountry,
    handleMaxUses,
    handleLimitUser,
    removeClick,
    handleSeeMore,
    handleModalOpenOnClick,
    length
}) => {
    const discountValueOptions = [
        { label: "Percentage", value: "percentage" },
        { label: "Fixed amount", value: "fixed_amount" },
        { label: "Free shipping", value: "free_shipping" }
    ];
    const appliesValueOptions = [
        { label: "Entire order", value: "*" },
        {
            label: "Specific collections",
            value: "specific_collections"
        },
        {
            label: "Specific product",
            value: "specific_product"
        }
    ];
    const requirmentValueOptions = [
        { label: "None", value: "none" },
        {
            label: "Minimum purchase amount",
            value: "minimum_purchase_amount"
        },
        {
            label: "Minimum quantity of items",
            value: "minimum_quantity_of_items"
        }
    ];
    const customerEligibilityOptions = [
        { label: "Everyone", value: "*" },
        {
            label: "Specific group of customers",
            value: "specific_group_customer"
        },
        {
            label: "Specific  customers",
            value: "specific_customer"
        }
    ];
    const countriesOptions = [
        { label: "All countries", value: "*" },
        {
            label: "Specific countries",
            value: "specific_countries"
        }
    ];

    return (
        <Fragment>
            <Card sectioned>
                <div className="form__layout">
                    <div className="form__group">
                        <div className="form__field ">
                            <InputField
                                type="text"
                                label="Rule name"
                                value={el.name}
                                placeholder="e.g. SPRINGSALES"
                                name="name"
                                onChange={e => handleInputChange(e, idx)}
                                error={el.error.name && el.error.name}
                            />
                        </div>
                        <div className="form__field">
                            <div className="form__field-wrapper">
                                <TextField
                                    type="text"
                                    label="Rule type"
                                    value="Discount"
                                />
                                {length > 1 && (
                                    <button
                                        className="icon link__btn"
                                        onClick={() => removeClick(idx)}
                                    >
                                        <Icon source={DeleteMajorMonotone} />
                                    </button>
                                )}
                            </div>
                        </div>
                    </div>
                    {el.isOpen && (
                        <div className="see_more">
                            <div className="flex__wrapper">
                                <div className="flex__item">
                                    <SelectField
                                        name="discount_type"
                                        options={discountValueOptions}
                                        onChange={e =>
                                            handleSelectChange(e, idx)
                                        }
                                        value={el.discount_type}
                                        label="At discount value"
                                        error={
                                            el.error.discount_type &&
                                            el.error.discount_type
                                        }
                                    />
                                </div>

                                <div className="flex__item">
                                    {el.discount_type === "free_shipping" && (
                                        <Fragment>
                                            <div className="field__item">
                                                <div className="flex__item-wrapper">
                                                    <div className="flex_one">
                                                        <SelectField
                                                            name="select_country"
                                                            options={
                                                                countriesOptions
                                                            }
                                                            onChange={e =>
                                                                handleSelect(
                                                                    e,
                                                                    idx
                                                                )
                                                            }
                                                            value={
                                                                el.select_country
                                                            }
                                                            label="Countries"
                                                            error={
                                                                el.error
                                                                    .select_country &&
                                                                el.error
                                                                    .select_country
                                                            }
                                                        />
                                                    </div>
                                                    <div className="browse__btn">
                                                        <div>
                                                            <Button
                                                                onClick={() =>
                                                                    handleModalOpenOnClick(
                                                                        idx,
                                                                        "country"
                                                                    )
                                                                }
                                                            >
                                                                Browse
                                                            </Button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div className="field__item">
                                                {el.countries_applicable &&
                                                el.countries_applicable.length >
                                                    0
                                                    ? el.countries_applicable.map(
                                                          el => (
                                                              <div className="customer__checked-list">
                                                                  <div className="name">
                                                                      {el.name}
                                                                  </div>

                                                                  <div className="remove__btn">
                                                                      <button
                                                                          onClick={() =>
                                                                              removeSelectedCountry(
                                                                                  idx,
                                                                                  el.id
                                                                              )
                                                                          }
                                                                      >
                                                                          <Icon
                                                                              source={
                                                                                  CancelSmallMinor
                                                                              }
                                                                          />
                                                                      </button>
                                                                  </div>
                                                              </div>
                                                          )
                                                      )
                                                    : ""}
                                            </div>
                                            <div className="field__item">
                                                <label className="Polaris-Label Polaris-Labelled__LabelWrapper">
                                                    Shipping rates
                                                </label>
                                                <Checkbox label="Exclude shipping rates over a certain amount" />
                                            </div>
                                        </Fragment>
                                    )}
                                    {el.discount_type !== "free_shipping" && (
                                        <div className="field__item">
                                            <InputField
                                                type="number"
                                                suffix={
                                                    el.discount_type ===
                                                    "percentage"
                                                        ? "%"
                                                        : null
                                                }
                                                prefix={
                                                    el.discount_type ===
                                                    "fixed_amount"
                                                        ? "RM"
                                                        : null
                                                }
                                                label="Value"
                                                value={el.discount_value}
                                                name="discount_value"
                                                onChange={e =>
                                                    handleInputChange(e, idx)
                                                }
                                                error={
                                                    el.error.discount_value &&
                                                    el.error.discount_value
                                                }
                                            />
                                        </div>
                                    )}

                                    <div className="field__item">
                                        <div className="flex__item-wrapper">
                                            <div className="flex_one">
                                                <SelectField
                                                    name="applies_to"
                                                    options={
                                                        appliesValueOptions
                                                    }
                                                    onChange={e =>
                                                        handleSelect(e, idx)
                                                    }
                                                    value={el.applies_to}
                                                    label="Applies to"
                                                    error={
                                                        el.error.applies_to &&
                                                        el.error.applies_to
                                                    }
                                                />
                                            </div>
                                            <div className="browse__btn">
                                                <div>
                                                    <Button
                                                        onClick={() =>
                                                            handleModalOpenOnClick(
                                                                idx,
                                                                "collection"
                                                            )
                                                        }
                                                    >
                                                        Browse
                                                    </Button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div className="field__item">
                                        {el.applied_ids &&
                                        el.applied_ids.length > 0
                                            ? el.applied_ids.map(el => (
                                                  <div className="customer__checked-list">
                                                      <div className="name">
                                                          {el.title}
                                                      </div>

                                                      <div className="remove__btn">
                                                          <button
                                                              onClick={() =>
                                                                  removeSelectedAppliedIds(
                                                                      idx,
                                                                      el.id
                                                                  )
                                                              }
                                                          >
                                                              <Icon
                                                                  source={
                                                                      CancelSmallMinor
                                                                  }
                                                              />
                                                          </button>
                                                      </div>
                                                  </div>
                                              ))
                                            : ""}
                                    </div>
                                    <div className="field__item">
                                        <div className="flex__row">
                                            <SelectField
                                                name="min_requirements"
                                                options={requirmentValueOptions}
                                                onChange={e =>
                                                    handleSelectChange(e, idx)
                                                }
                                                value={el.min_requirements}
                                                label="Minimum requirment"
                                                error={
                                                    el.error.min_requirements &&
                                                    el.error.min_requirements
                                                }
                                            />
                                            {(el.min_requirements ===
                                                "minimum_purchase_amount" ||
                                                el.min_requirements ===
                                                    "minimum_quantity_of_items") && (
                                                <div className="min__value">
                                                    <InputField
                                                        type="number"
                                                        prefix={
                                                            el.min_requirements ===
                                                            "minimum_purchase_amount"
                                                                ? "RM"
                                                                : null
                                                        }
                                                        label={
                                                            el.min_requirements ===
                                                            "minimum_quantity_of_items"
                                                                ? "Quantity"
                                                                : "Value"
                                                        }
                                                        value={el.min_req_value}
                                                        name="min_req_value"
                                                        onChange={e =>
                                                            handleInputChange(
                                                                e,
                                                                idx
                                                            )
                                                        }
                                                        error={
                                                            el.error
                                                                .min_req_value &&
                                                            el.error
                                                                .min_req_value
                                                        }
                                                    />
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                    <div className="field__item">
                                        <div className="flex__item-wrapper">
                                            <div className="flex_one">
                                                <SelectField
                                                    name="customer_eligibility"
                                                    options={
                                                        customerEligibilityOptions
                                                    }
                                                    onChange={e =>
                                                        handleSelect(e, idx)
                                                    }
                                                    value={
                                                        el.customer_eligibility
                                                    }
                                                    label="Customer eligibility"
                                                    error={
                                                        el.error
                                                            .customer_eligibility &&
                                                        el.error
                                                            .customer_eligibility
                                                    }
                                                />
                                            </div>
                                            <div className="browse__btn">
                                                <div>
                                                    <Button
                                                        onClick={() =>
                                                            handleModalOpenOnClick(
                                                                idx,
                                                                "customer"
                                                            )
                                                        }
                                                    >
                                                        Browse
                                                    </Button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div className="field__item">
                                        {el.eligible_customers &&
                                        el.eligible_customers.length > 0
                                            ? el.eligible_customers.map(el => (
                                                  <div className="customer__checked-list">
                                                      <div className="name">
                                                          {el.first_name &&
                                                              el.first_name}{" "}
                                                          {el.last_name &&
                                                              el.last_name}
                                                          {el.name && el.name}
                                                      </div>

                                                      <div className="remove__btn">
                                                          <button
                                                              onClick={() =>
                                                                  removeSelectedCustomer(
                                                                      idx,
                                                                      el.id
                                                                  )
                                                              }
                                                          >
                                                              <Icon
                                                                  source={
                                                                      CancelSmallMinor
                                                                  }
                                                              />
                                                          </button>
                                                      </div>
                                                  </div>
                                              ))
                                            : ""}
                                    </div>
                                    <div className="field__item">
                                        <Checkbox
                                            label="Limit number of times this discount can be used in total"
                                            checked={el.max_uses}
                                            onChange={e =>
                                                handleMaxUses(e, idx)
                                            }
                                        />
                                        {el.max_uses && (
                                            <div className="col-5">
                                                <InputField
                                                    type="text"
                                                    value={el.max_no_of_uses}
                                                    name="max_no_of_uses"
                                                    onChange={e =>
                                                        handleInputChange(
                                                            e,
                                                            idx
                                                        )
                                                    }
                                                />
                                            </div>
                                        )}
                                        <InlineError
                                            message={
                                                el.error.max_no_of_uses &&
                                                el.error.max_no_of_uses
                                            }
                                        />
                                    </div>
                                    <div className="field__item">
                                        <Checkbox
                                            label="Limit to one use per customer"
                                            checked={
                                                el.limit_to_one_use_per_customer
                                            }
                                            onChange={e =>
                                                handleLimitUser(e, idx)
                                            }
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                    )}
                    <div className="text-center">
                        <SeeMoreButton
                            seeMoreText="set rule"
                            seeLessText="hide rule"
                            isOpen={el.isOpen}
                            click={() => handleSeeMore(idx)}
                        />
                    </div>
                </div>
            </Card>
        </Fragment>
    );
};

export default Form;
