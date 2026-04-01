import { useMemo, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useCart } from '../context/CartContext'

function formatCurrency(value) {
  return `$${Number(value || 0).toFixed(2)}`
}

export default function CheckoutPage() {
  const navigate = useNavigate()
  const { cart, totals, placeOrder, loading } = useCart()
  const [address, setAddress] = useState('101 Customer Ave')
  const [note, setNote] = useState('Leave at the door')
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState('')

  const isCartEmpty = useMemo(() => (cart.items || []).length === 0, [cart.items])

  const onSubmit = async (event) => {
    event.preventDefault()
    setError('')
    setSubmitting(true)

    try {
      const order = await placeOrder({
        delivery_address: address,
        customer_note: note,
      })
      navigate(`/orders/${order.id}/track`)
    } catch (checkoutError) {
      setError(checkoutError?.response?.data?.message || 'Checkout failed.')
    } finally {
      setSubmitting(false)
    }
  }

  if (loading) {
    return <p>Loading checkout...</p>
  }

  if (isCartEmpty) {
    return <p>Your cart is empty. Add menu items before checkout.</p>
  }

  return (
    <section>
      <h1>Checkout</h1>
      <div className="checkout-layout">
        <form className="card form-card" onSubmit={onSubmit}>
          <h2>Delivery details</h2>
          <label>
            Delivery address
            <textarea
              value={address}
              onChange={(event) => setAddress(event.target.value)}
              rows={3}
              required
            />
          </label>

          <label>
            Order note
            <textarea
              value={note}
              onChange={(event) => setNote(event.target.value)}
              rows={3}
            />
          </label>

          {error && <p className="error-text">{error}</p>}
          <button type="submit" disabled={submitting}>
            {submitting ? 'Processing...' : 'Confirm order'}
          </button>
        </form>

        <aside className="card summary-card">
          <h2>Order summary</h2>
          <ul className="summary-list">
            {cart.items.map((item) => (
              <li key={item.id}>
                <span>
                  {item.menu_item?.name || item.name} x {item.quantity}
                </span>
                <span>{formatCurrency(item.line_total)}</span>
              </li>
            ))}
          </ul>
          <p>Subtotal: {formatCurrency(totals.subtotal)}</p>
          <p>Tax: {formatCurrency(totals.tax_amount)}</p>
          <p>Delivery: {formatCurrency(totals.delivery_fee)}</p>
          <p className="total-row">Total: {formatCurrency(totals.total_amount)}</p>
        </aside>
      </div>
    </section>
  )
}
