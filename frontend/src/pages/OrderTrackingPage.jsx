import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { getOrderTrack } from '../api/client';

const statusSteps = ['pending', 'accepted', 'preparing', 'delivered'];

export default function OrderTrackingPage() {
  const { orderId = '' } = useParams();
  const [order, setOrder] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    let ignore = false;
    const fetchOrder = async () => {
      setLoading(true);
      setError('');
      try {
        const data = await getOrderTrack(orderId);
        if (!ignore) {
          setOrder(data);
        }
      } catch (err) {
        if (!ignore) {
          setError(err?.response?.data?.message || 'Unable to load order status.');
        }
      } finally {
        if (!ignore) {
          setLoading(false);
        }
      }
    };

    fetchOrder();
    const interval = setInterval(fetchOrder, 5000);
    return () => {
      ignore = true;
      clearInterval(interval);
    };
  }, [orderId]);

  if (loading) {
    return <p>Loading order status...</p>;
  }

  if (error) {
    return <p className="error-text">{error}</p>;
  }

  if (!order) {
    return <p>Order not found.</p>;
  }

  return (
    <section>
      <h2>Order #{order.id} tracking</h2>
      <p className="muted">Restaurant: {order.restaurant?.name}</p>
      <div className="status-track">
        {statusSteps.map((step) => {
          const reached = statusSteps.indexOf(order.status) >= statusSteps.indexOf(step);
          return (
            <div key={step} className={`status-step ${reached ? 'reached' : ''}`}>
              {step}
            </div>
          );
        })}
      </div>

      <h3>Status history</h3>
      <ul className="history">
        {(order.status_history || []).map((item) => (
          <li key={item.id}>
            <strong>{item.status}</strong> at{' '}
            {new Date(item.changed_at || item.created_at).toLocaleString()}
          </li>
        ))}
      </ul>

      <div className="actions">
        <Link to="/cart" className="button">
          Back to cart
        </Link>
      </div>
    </section>
  );
}
