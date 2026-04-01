import axios from 'axios'

const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000/api'

export const api = axios.create({
  baseURL: API_BASE_URL,
  headers: {
    'Content-Type': 'application/json',
  },
})

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('foodhub_token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }

  return config
})

export async function getRestaurants() {
  const response = await api.get('/restaurants')
  return response.data.data ?? []
}

export async function getMenuByRestaurant(restaurantId) {
  const response = await api.get(`/restaurants/${restaurantId}/menu-items`)
  const payload = response.data?.data
  if (payload?.data) {
    return payload.data
  }

  if (Array.isArray(payload)) {
    return payload
  }

  return []
}

export async function getCart() {
  const response = await api.get('/cart')
  return response.data
}

export async function addCartItem(menuItemId, quantity = 1) {
  const response = await api.post('/cart/items', {
    menu_item_id: menuItemId,
    quantity,
  })
  return response.data
}

export async function updateCartItem(itemId, quantity) {
  const response = await api.patch(`/cart/items/${itemId}`, { quantity })
  return response.data
}

export async function removeCartItem(itemId) {
  const response = await api.delete(`/cart/items/${itemId}`)
  return response.data
}

export async function placeOrder(payload) {
  const response = await api.post('/orders', payload)
  return response.data
}

export async function createOrder(payload) {
  return placeOrder(payload)
}

export async function clearCart() {
  await api.delete('/cart')
}

export async function getOrderTrack(orderId) {
  const response = await api.get(`/orders/${orderId}/track`)
  return response.data
}

export default api
