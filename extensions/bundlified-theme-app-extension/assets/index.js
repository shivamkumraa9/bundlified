async function addToCart(parentProductId, freeProductId) {
    document.querySelector('.bundlified').textContent = 'Adding.'
    function extractProductId(gid) {
        const match = gid.match(/(\d+)$/);
        return match ? match[0] : null;
    }

    const parentProductVariantId = extractProductId(parentProductId);
    const freeProductVariantId = extractProductId(freeProductId);

    if (!parentProductVariantId || !freeProductVariantId) {
        console.error('Invalid product IDs.');
        return;
    }

    let formData = {
        'items': [
            {
                'id': parentProductVariantId,
                'quantity': 1
            },
            {
                'id': freeProductVariantId,
                'quantity': 1
            }
        ]
    };

    await fetch(window.Shopify.routes.root + 'cart/add.js', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(formData)
    })

    document.querySelector('.bundlified').textContent = 'Add Both to Get 1 Free!'

}


function injectAddBothProductButton(data) {
    const button = document.createElement('button');
    
    button.textContent = 'Add Both to Get 1 Free!';
    button.style.width = '100%';
    button.style.marginTop = '10px';
    button.classList.add('button', 'button--secondary', 'bundlified');
    button.addEventListener('click', () => addToCart(data.parentId, data.id))
    
    const offerMessage = document.createElement('p');
    
    offerMessage.textContent = `Free "${data.title}" is included in this offer 🔥`;
    
    offerMessage.style.fontSize = '14px';
    offerMessage.style.marginTop = '10px';
    offerMessage.style.color = '#d9534f';

    const form = document.querySelector('.form');
    
    if (form) {
        form.insertAdjacentElement('afterend', offerMessage);
        form.insertAdjacentElement('afterend', button);
    } else {
        console.error('Form with class .form not found!');
    }
}

(async () => {
    try {
        if (ShopifyAnalytics?.meta?.product?.gid) {
            const productGid = ShopifyAnalytics.meta.product.gid;

            const shop = Shopify?.shop;

            const apiUrl = `https://destined-strictly-lacewing.ngrok-free.app/api/getProduct.php?shop=${shop}&productId=${encodeURIComponent(productGid)}`;

            const response = await fetch(apiUrl, {
                method: "GET",
                headers: {
                    "ngrok-skip-browser-warning": "1",
                },
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();

            if (data?.product?.title) {
                injectAddBothProductButton(data?.product)
                console.log(`Product exists: ${data.product.title}`);
            } else {
                console.log("Product does not exist.");
            }
        } else {
            console.log("Product GID not found.");
        }
    } catch (error) {
        console.error("Error fetching product data:", error);
    }
})();
