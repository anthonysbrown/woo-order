import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { getRestaurants } from '../api/client'

export default function RestaurantListPage() {
  const [restaurants, setRestaurants] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  useEffect(() => {
    const fetchRestaurants = async () => {
      try {
        const data = await getRestaurants()
        setRestaurants(data)
      } catch (err) {
        setError(err.response?.data?.message ?? 'Failed to load restaurants.')
      } finally {
        setLoading(false)
      }
    }

    fetchRestaurants()
  }, [])

  if (loading) return <p>Loading restaurants...</p>
  if (error) return <p className="error-text">{error}</p>

  return (
    <section className="page">
      <h2>Restaurants</h2>
      <div className="card-grid">
        {restaurants.map((restaurant) => (
          <article className="card" key={restaurant.id}>
            <h3>{restaurant.name}</h3>
            <p>{restaurant.description}</p>
            <p className="muted">{restaurant.address}</p>
            <p className="badge">{restaurant.menu_items_count} menu items</p>
            <Link to={`/restaurants/${restaurant.id}/menu`}>View menu</Link>
          </article>
        ))}
      </div>
    </section>
  )
}
