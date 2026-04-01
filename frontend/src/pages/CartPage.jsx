import { useEffect, useMemo, useState } from 'react';
import {
  clearCart,
  getCart,
  placeOrder as placeOrderRequest,
  removeCartItem,
  updateCartItem,
} from '../api/client';
import { Link } from 'react-router-dom';

function formatCurrency(value) {
  return `$${Number(value || 0).toFixed(2)}`;
}

export default function CartPage() {
  const [cartData, setCartData] = useState({ cart: { items: [] }, totals: {} });
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [placingOrder, setPlacingOrder] = useState(false);
  const [lastOrder, setLastOrder] = useState(null);

  const loadCart = async () => {
    setLoading(true);
    setError('');

    try {
      const data = await getCart();
      setCartData(data);
    } catch (err) {
      setError(err?.response?.data?.message || 'Failed to load cart.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadCart();
  }, []);

  const isEmpty = useMemo(() => (cartData.cart.items || []).length === 0, [cartData]);

  const updateQuantity = async (itemId, quantity) => {
    try {
      const data = await updateCartItem(itemId, quantity);
      setCartData(data);
    } catch (err) {
      alert(err?.response?.data?.message || 'Unable to update quantity.');
    }
  };

  const removeItem = async (itemId) => {
    try {
      const data = await removeCartItem(itemId);
      setCartData(data);
    } catch (err) {
      alert(err?.response?.data?.message || 'Unable to remove item.');
    }
  };

  const handlePlaceOrder = async () => {
    setPlacingOrder(true);
    setError('');

    try {
      const order = await placeOrderRequest({
        delivery_address: '101 Customer Ave',
        customer_note: 'Mock order from React demo',
      });
      setLastOrder(order);
      await loadCart();
    } catch (err) {
      setError(err?.response?.data?.message || 'Order creation failed.');
    } finally {
      setPlacingOrder(false);
    }
  };

  if (loading) {
    return <p>Loading cart...</p>;
  }

  return (
    <section>
      <h1>Cart</h1>
      <p>Review your order before placing it.</p>

      {error && <p className="error-text">{error}</p>}

      {isEmpty ? (
        <p>Your cart is empty.</p>
      ) : (
        <>
          <div className="card-list">
            {cartData.cart.items.map((item) => (
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
            <p>Subtotal: {formatCurrency(cartData.totals.subtotal)}</p>
            <p>Delivery fee: {formatCurrency(cartData.totals.delivery_fee)}</p>
            <p>Tax: {formatCurrency(cartData.totals.tax_amount)}</p>
            <p>
              <strong>Total: {formatCurrency(cartData.totals.total_amount)}</strong>
            </p>
            <button onClick={handlePlaceOrder} disabled={placingOrder}>
              {placingOrder ? 'Placing...' : 'Place order'}
            </button>
            <button
              onClick={async () => {
                await clearCart();
                await loadCart();
              }}
              className="secondary-btn"
            >
              Clear cart
            </button>
          </div>
        </>
      )}

      {lastOrder && (
        <div className="success-banner">
          Order #{lastOrder.id} placed.{' '}
          <Link to={`/orders/${lastOrder.id}/track`}>Track order</Link>
        </div>
      )}
    </section>
  );
}
