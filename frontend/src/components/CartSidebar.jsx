import { Link, useNavigate } from 'react-router-dom'
import { useCart } from '../context/CartContext'

function formatMoney(value) {
  return `$${Number(value || 0).toFixed(2)}`
}

export default function CartSidebar() {
  const navigate = useNavigate()
  const { itemCount, totals, cart, status, error, loadCart, clearCartItems } = useCart()

  const lineItems = cart?.items || []

  return (
    <aside className="cart-sidebar">
      <div className="cart-sidebar-header">
        <h3>Cart</h3>
        <span className="badge">{itemCount} items</span>
      </div>

      {status === 'loading' ? <p className="muted">Loading cart...</p> : null}
      {error ? <p className="error-text">{error}</p> : null}

      {lineItems.length === 0 ? (
        <p className="muted">No items in cart.</p>
      ) : (
        <ul className="cart-mini-list">
          {lineItems.slice(0, 4).map((item) => (
            <li key={item.id} className="cart-mini-item">
              <span>{item.menu_item?.name || 'Item'}</span>
              <span>x{item.quantity}</span>
            </li>
          ))}
          {lineItems.length > 4 ? (
            <li className="muted">+{lineItems.length - 4} more items</li>
          ) : null}
        </ul>
      )}

      <div className="cart-summary">
        <div className="row">
          <span>Subtotal</span>
          <strong>{formatMoney(totals.subtotal)}</strong>
        </div>
        <div className="row">
          <span>Tax</span>
          <strong>{formatMoney(totals.tax_amount)}</strong>
        </div>
        <div className="row">
          <span>Fee</span>
          <strong>{formatMoney(totals.delivery_fee)}</strong>
        </div>
        <div className="row">
          <span>Total</span>
          <strong>{formatMoney(totals.total_amount)}</strong>
        </div>
      </div>

      <div className="cart-sidebar-actions">
        <button className="button" onClick={() => navigate('/checkout')} disabled={!itemCount}>
          Checkout
        </button>
        <Link className="button" to="/cart">
          Open cart
        </Link>
        <button
          className="button"
          onClick={async () => {
            await clearCartItems()
            await loadCart()
          }}
          disabled={!itemCount}
        >
          Clear
        </button>
      </div>
    </aside>
  )
}
