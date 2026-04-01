import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { addCartItem, getMenuByRestaurant } from '../api/client';

export default function MenuPage() {
  const { restaurantId } = useParams();
  const [items, setItems] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [message, setMessage] = useState('');

  useEffect(() => {
    const loadMenu = async () => {
      try {
        setLoading(true);
        const data = await getMenuByRestaurant(restaurantId);
        setItems(data);
      } catch (err) {
        setError(err.response?.data?.message || 'Failed to load menu.');
      } finally {
        setLoading(false);
      }
    };

    loadMenu();
  }, [restaurantId]);

  const onAdd = async (menuItemId) => {
    setMessage('');
    try {
      await addCartItem(menuItemId, 1);
      setMessage('Added to cart.');
    } catch (err) {
      setMessage(err.response?.data?.message || 'Unable to add item. Make sure you are logged in as customer.');
    }
  };

  return (
    <section className="page">
      <h1>Restaurant Menu</h1>
      <p>
        <Link to="/cart">Go to cart</Link>
      </p>
      {loading && <p>Loading menu...</p>}
      {error && <p className="error">{error}</p>}
      {message && <p className="info">{message}</p>}
      <div className="grid">
        {items.map((item) => (
          <article className="card" key={item.id}>
            <h3>{item.name}</h3>
            <p className="muted">{item.description || 'No description'}</p>
            <p className="strong">${item.price}</p>
            <button onClick={() => onAdd(item.id)}>Add to cart</button>
          </article>
        ))}
      </div>
    </section>
  );
}
