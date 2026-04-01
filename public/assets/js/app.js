document.addEventListener('alpine:init', () => {

  // ===== STORE PANIER =====
  Alpine.store('cart', {
    items: [],  // [{key, id, name, price, qty, options, unitTotal}]

    get count() {
      return this.items.reduce((s, i) => s + i.qty, 0);
    },
    get subtotal() {
      return this.items.reduce((s, i) => s + i.unitTotal * i.qty, 0);
    },

    addItem(product, options, qty) {
      const key = product.id + '-' + JSON.stringify(options.map(o => o.id).sort());
      const optExtra = options.reduce((s, o) => s + (parseFloat(o.extra_price) || 0), 0);
      const unitTotal = parseFloat(product.price) + optExtra;
      const existing = this.items.find(i => i.key === key);
      if (existing) {
        existing.qty += qty;
      } else {
        this.items.push({
          key,
          id: product.id,
          name: product.name,
          price: product.price,
          qty,
          options,
          unitTotal,
        });
      }
    },

    updateQty(key, delta) {
      const item = this.items.find(i => i.key === key);
      if (!item) return;
      item.qty = Math.max(0, item.qty + delta);
      if (item.qty === 0) this.items = this.items.filter(i => i.key !== key);
    },

    clear() { this.items = []; },
  });

});
