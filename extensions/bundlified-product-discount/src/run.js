// @ts-check
import { DiscountApplicationStrategy } from "../generated/api";

/**
 * @typedef {import("../generated/api").RunInput} RunInput
 * @typedef {import("../generated/api").FunctionRunResult} FunctionRunResult
 */

/**
 * @type {FunctionRunResult}
 */
const EMPTY_DISCOUNT = {
  discountApplicationStrategy: DiscountApplicationStrategy.First,
  discounts: [],
};

/**
 * @param {RunInput} input
 * @returns {FunctionRunResult}
 */
export function run(input) {
  const discounts = [];
  
  input.cart.lines.forEach((line) => {
    const metafieldValue = line.merchandise?.product?.metafield?.value;

    if (metafieldValue) {
      try {
        const metafieldJson = JSON.parse(metafieldValue);

        const targetProductId = metafieldJson?.parentId;
        const freeProductVariantId = metafieldJson?.id;

        if (targetProductId && freeProductVariantId) {
          const targetCartLine = input.cart.lines.find(
            (line) => line.merchandise.id === targetProductId
          );

          if (targetCartLine) {
            const mainProductQuantity = targetCartLine.quantity;

            discounts.push({
              targets: [
                {
                  productVariant: {
                    id: freeProductVariantId,
                    quantity: mainProductQuantity,
                  },
                },
              ],
              value: {
                percentage: {
                  value: 100,
                },
              },
            });
          }
        }
      } catch (error) {
        console.error("Error parsing metafield value:", error);
      }
    }
  });

  if (discounts.length === 0) {
    return EMPTY_DISCOUNT
  }

  return {
    discounts,
    discountApplicationStrategy: DiscountApplicationStrategy.First,
  };
}
