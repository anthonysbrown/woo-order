import { Link } from 'react-router-dom';
import { useCart } from '../context/CartContext';

function formatCurrency(value) {
  return `$${Number(value || 0).toFixed(2)}`;
}

export default function CartPage() {
  const {
    cart,
    totals,
    loading,
    error,
    loadCart,
    updateQuantity,
    removeItem,
    clearAll,
  } = useCart();

  if (loading) {
    return <p>Loading cart...</p>;
  }

  const items = cart?.items ?? [];
  const isEmpty = items.length === 0;

  return (
    <section>
      <h1>Cart</h1>
      <p>Review your order before checkout.</p>

      {error && <p className="error-text">{error}</p>}

      {isEmpty ? (
        <p>Your cart is empty.</p>
      ) : (
        <>
          <div className="card-list">
            {items.map((item) => (
              <article key={item.id} className="card">
                <h3>{item.menu_item?.name || 'Menu Item'}</h3>
                <p>{formatCurrency(item.unit_price)} each</p>
                <p>Line total: {formatCurrency(item.line_total)}</p>
                <div className="row">
                  <button onClick={() => updateQuantity(item.id, Math.max(1, item.quantity - 1))}>-</button>
                  <span>Qty: {item.quantity}</span>
                  <button onClick={() => updateQuantity(item.id, item.quantity + 1)}>+</button>
                  <button onClick={() => removeItem(item.id)}>Remove</button>
                </div>
              </article>
            ))}
          </div>

          <div className="totals-card">
            <h3>Summary</h3>
            <p>Subtotal: {formatCurrency(totals.subtotal)}</p>
            <p>Delivery fee: {formatCurrency(totals.delivery_fee)}</p>
            <p>Tax: {formatCurrency(totals.tax_amount)}</p>
            <p>
              <strong>Total: {formatCurrency(totals.total_amount)}</strong>
            </p>
            <Link to="/checkout" className="button primary">
              Proceed to checkout
            </Link>
            <button
              onClick={clearAll}
              className="secondary-btn"
            >
              Clear cart
            </button>
          </div>
        </>
      )}
    </section>
  );
}
