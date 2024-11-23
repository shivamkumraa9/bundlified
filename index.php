<?php

require_once __DIR__ . '/services/ShopifyService.php';
require_once __DIR__ . '/services/DatabaseService.php';

$databaseService = new DatabaseService();
$shopifyService = new ShopifyService();

$decodedToken = $shopifyService->verifyToken();

header('Content-Type: text/html');

if ($decodedToken) {
    $shopifyService->handleInstall();

    $shopData = $decodedToken['data']['shop_data'];
    $shop = parse_url($shopData['iss'], PHP_URL_HOST);

    try {
        $products = $shopifyService->fetchProducts($shop);
        $productsJson = json_encode($products, JSON_PRETTY_PRINT);
        // echo $productsJson;

        $filteredProducts = $products['filtered'];
        $excludedProducts = $products['excluded'];

        $productTitles = array_map(function ($product) {
            return $product['node']['title'] ?? 'Untitled Product';
        }, $filteredProducts);
?>
        <!DOCTYPE html>
        <html lang="en">

        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Products Fetched Successfully</title>
            <link href="https://cdn.jsdelivr.net/npm/@shopify/polaris@13.9.1/build/esm/styles.css" rel="stylesheet">
            <meta name="shopify-api-key" content="48ad50f2eb203fc722ade07271799938" />
            <script src="https://cdn.shopify.com/shopifycloud/app-bridge.js"></script>
        </head>

        <body>
            <div class="Polaris-Page">
                <div class="Polaris-Box" style="--pc-box-padding-block-start-xs:var(--p-space-400);--pc-box-padding-block-start-md:var(--p-space-600);--pc-box-padding-block-end-xs:var(--p-space-400);--pc-box-padding-block-end-md:var(--p-space-600);--pc-box-padding-inline-start-xs:var(--p-space-400);--pc-box-padding-inline-start-sm:var(--p-space-0);--pc-box-padding-inline-end-xs:var(--p-space-400);--pc-box-padding-inline-end-sm:var(--p-space-0);position:relative">
                </div>
                <div class="">
                    <h2 class="Polaris-Text--root Polaris-Text--headingLg">Bundlified</h2>
                    <div style="margin-top: 10px"></div>
                    <div>
                        <div class="Polaris-Labelled__LabelWrapper">
                            <div class="Polaris-Label">
                                <label id="ProductSelectLabel" class="Polaris-Label__Text">
                                    <span class="Polaris-Text--root Polaris-Text--bodyMd">Select Main Product</span>
                                </label>
                            </div>
                        </div>
                        <div class="Polaris-Select">
                            <select id="ProductSelect1" class="Polaris-Select__Input">
                                <?php foreach ($productTitles as $title): ?>
                                    <option value="<?php echo htmlspecialchars($title); ?>">
                                        <?php echo htmlspecialchars($title); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="Polaris-Select__Content" aria-hidden="true">
                                <span id="ProductSelect1-Text" class="Polaris-Select__SelectedOption"></span>
                                <span class="Polaris-Select__Icon">
                                    <span class="Polaris-Icon">
                                        <svg viewBox="0 0 20 20" class="Polaris-Icon__Svg" focusable="false" aria-hidden="true">
                                            <path d="M10.884 4.323a1.25 1.25 0 0 0-1.768 0l-2.646 2.647a.75.75 0 0 0 1.06 1.06l2.47-2.47 2.47 2.47a.75.75 0 1 0 1.06-1.06l-2.646-2.647Z"></path>
                                            <path d="m13.53 13.03-2.646 2.647a1.25 1.25 0 0 1-1.768 0l-2.646-2.647a.75.75 0 0 1 1.06-1.06l2.47 2.47 2.47-2.47a.75.75 0 0 1 1.06 1.06Z"></path>
                                        </svg>
                                    </span>
                                </span>
                            </div>
                            <div class="Polaris-Select__Backdrop"></div>
                        </div>
                    </div>
                    <div style="margin-top: 10px"></div>
                    <div>
                        <div class="Polaris-Labelled__LabelWrapper">
                            <div class="Polaris-Label">
                                <label id="ProductSelectLabel" class="Polaris-Label__Text">
                                    <span class="Polaris-Text--root Polaris-Text--bodyMd">Select Free Product</span>
                                </label>
                            </div>
                        </div>
                        <div class="Polaris-Select">
                            <select id="ProductSelect2" class="Polaris-Select__Input">
                                <?php foreach ($productTitles as $title): ?>
                                    <option value="<?php echo htmlspecialchars($title); ?>">
                                        <?php echo htmlspecialchars($title); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="Polaris-Select__Content" aria-hidden="true">
                                <span id="ProductSelect2-Text" class="Polaris-Select__SelectedOption">Select a product</span>
                                <span class="Polaris-Select__Icon">
                                    <span class="Polaris-Icon">
                                        <svg viewBox="0 0 20 20" class="Polaris-Icon__Svg" focusable="false" aria-hidden="true">
                                            <path d="M10.884 4.323a1.25 1.25 0 0 0-1.768 0l-2.646 2.647a.75.75 0 0 0 1.06 1.06l2.47-2.47 2.47 2.47a.75.75 0 1 0 1.06-1.06l-2.646-2.647Z"></path>
                                            <path d="m13.53 13.03-2.646 2.647a1.25 1.25 0 0 1-1.768 0l-2.646-2.647a.75.75 0 0 1 1.06-1.06l2.47 2.47 2.47-2.47a.75.75 0 0 1 1.06 1.06Z"></path>
                                        </svg>
                                    </span>
                                </span>
                            </div>
                            <div class="Polaris-Select__Backdrop"></div>
                        </div>
                    </div>
                    <div style="margin-top: 5px"></div>
                    <p style="color: red;" id="error-text"></p>
                    <div style="margin-top: 10px"></div>
                    <button id="submit-btn" class="Polaris-Button Polaris-Button--pressable Polaris-Button--variantPrimary Polaris-Button--sizeMedium Polaris-Button--textAlignCenter" type="button">
                        <span class="Polaris-Text--root Polaris-Text--bodySm Polaris-Text--medium">Create Bundle</span>
                    </button>
                    <div style="margin-top: 15px"></div>
                    <div class="Polaris-ShadowBevel" style="<?php if (empty($excludedProducts)) echo 'display: none;'; ?> --pc-shadow-bevel-z-index: 32; --pc-shadow-bevel-content-xs: &quot;&quot;; --pc-shadow-bevel-box-shadow-xs: var(--p-shadow-100); --pc-shadow-bevel-border-radius-xs: var(--p-border-radius-300);">
                        <div class="Polaris-Box" style="--pc-box-background:var(--p-color-bg-surface);--pc-box-min-height:100%;--pc-box-overflow-x:clip;--pc-box-overflow-y:clip;--pc-box-padding-block-start-xs:var(--p-space-400);--pc-box-padding-block-end-xs:var(--p-space-400);--pc-box-padding-inline-start-xs:var(--p-space-400);--pc-box-padding-inline-end-xs:var(--p-space-400)">
                            <h2 class="Polaris-Text--root Polaris-Text--headingXl">Your Bundles</h2>
                            <div style="margin-top: 6px"></div>

                            <ul class="Polaris-List">
                                <?php foreach ($excludedProducts as $product): ?>
                                    <li class="Polaris-List__Item">
                                        <?php echo htmlspecialchars($product['node']['title']); ?>
                                        X
                                        <?php
                                        $bundlifiedMetafield = null;
                                        foreach ($product['node']['metafields']['edges'] as $metafieldEdge) {
                                            if ($metafieldEdge['node']['key'] === 'bundlified_free_product_id') {
                                                $bundlifiedMetafield = $metafieldEdge['node'];
                                                break;
                                            }
                                        }

                                        if ($bundlifiedMetafield) {
                                            $bundlifiedData = json_decode($bundlifiedMetafield['value'], true);
                                            $bundlifiedTitle = $bundlifiedData['title'] ?? 'N/A';
                                        }
                                        ?>

                                        <?php echo htmlspecialchars($bundlifiedTitle); ?>

                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            <script>
                function updateSelectedOptionText(productSelect, selectedOptionText) {
                    const selectedOption = productSelect.options[productSelect.selectedIndex];
                    selectedOptionText.textContent = selectedOption ? selectedOption.textContent : "Select a product";
                }

                function addListItem(content) {
                    const ulElement = document.querySelector('.Polaris-List');
                    const newListItem = document.createElement('li');
                    newListItem.className = 'Polaris-List__Item';
                    newListItem.textContent = content;
                    ulElement.appendChild(newListItem);
                }

                async function deleteSelectedOption() {
                    const selectedProduct1 = document.getElementById('ProductSelect1').value;
                    const selectedProduct2 = document.getElementById('ProductSelect2').value;

                    const selectedProduct = filteredProducts.find(product => product.node.title === selectedProduct1);
                    const excludedProduct = filteredProducts.find(product => product.node.title === selectedProduct2);
                    const payload = {
                        product: selectedProduct.node.id,
                        datastring: JSON.stringify({
                            parentId: selectedProduct.node.variants.edges[0].node.id,
                            id: excludedProduct.node.variants.edges[0].node.id,
                            title: excludedProduct.node.title
                        })
                    };

                    document.getElementById('error-text').textContent = ''

                    if (selectedProduct1 === selectedProduct2) {
                        document.getElementById('error-text').textContent = 'Both products cannot be the same!'
                        return;
                    }
                    try {
                        const token = await shopify.idToken()
                        const response = await fetch('api/createBundle.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Authorization': `${token}`
                            },
                            body: JSON.stringify(payload),
                        });
                        if (response.ok) {
                            const result = await response.json();
                            console.log('API Response:', result);
                            addListItem(`${selectedProduct1} X ${selectedProduct2}`)
                            const selectElement = document.getElementById('ProductSelect1');
                            selectElement.remove(selectElement.selectedIndex);
                            const selectElement2 = document.getElementById('ProductSelect1');
                            selectedOptionText1.textContent = selectElement2.options[selectElement2.selectedIndex].textContent
                            document.querySelector(".Polaris-ShadowBevel").style.display = 'block'

                        } else {
                            console.error('API Error:', response.status, response.statusText);
                        }
                    } catch (err) {
                        console.error(err)

                    }
                }
                const productSelect1 = document.getElementById('ProductSelect1');
                const selectedOptionText1 = document.getElementById('ProductSelect1-Text');

                const productSelect2 = document.getElementById('ProductSelect2');
                const selectedOptionText2 = document.getElementById('ProductSelect2-Text');

                const filteredProducts = <?php echo json_encode($filteredProducts); ?>;
                const excludedProducts = <?php echo json_encode($excludedProducts); ?>;

                updateSelectedOptionText(productSelect1, selectedOptionText1)
                updateSelectedOptionText(productSelect2, selectedOptionText2)
                productSelect1.addEventListener('change', () => updateSelectedOptionText(productSelect1, selectedOptionText1));
                productSelect2.addEventListener('change', () => updateSelectedOptionText(productSelect2, selectedOptionText2));

                document.getElementById('submit-btn').addEventListener('click', deleteSelectedOption)
            </script>
        </body>

        </html>
    <?php
    } catch (Exception $e) {
    ?>
<?php
    }
}
?>