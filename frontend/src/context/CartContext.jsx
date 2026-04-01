import { createContext, useCallback, useContext, useMemo, useReducer } from 'react';
import {
  addCartItem,
  clearCart,
  getCart,
  placeOrder,
  removeCartItem,
  updateCartItem,
} from '../api/client';

const CartContext = createContext(null);

const initialState = {
  cart: { items: [] },
  totals: {
    subtotal: 0,
    delivery_fee: 0,
    tax_amount: 0,
    total_amount: 0,
  },
  loading: false,
  checkoutLoading: false,
  error: '',
  lastOrder: null,
};

function reducer(state, action) {
  switch (action.type) {
    case 'SET_LOADING':
      return { ...state, loading: action.payload };
    case 'SET_CHECKOUT_LOADING':
      return { ...state, checkoutLoading: action.payload };
    case 'SET_CART':
      return {
        ...state,
        cart: action.payload.cart ?? initialState.cart,
        totals: action.payload.totals ?? initialState.totals,
      };
    case 'SET_ERROR':
      return { ...state, error: action.payload };
    case 'SET_LAST_ORDER':
      return { ...state, lastOrder: action.payload };
    default:
      return state;
  }
}

function createIdempotencyKey() {
  return `checkout-${Date.now()}-${Math.random().toString(16).slice(2, 10)}`;
}

export function CartProvider({ children }) {
  const [state, dispatch] = useReducer(reducer, initialState);

  const loadCart = useCallback(async () => {
    dispatch({ type: 'SET_LOADING', payload: true });
    dispatch({ type: 'SET_ERROR', payload: '' });
    try {
      const payload = await getCart();
      dispatch({ type: 'SET_CART', payload });
    } catch (error) {
      dispatch({
        type: 'SET_ERROR',
        payload: error?.response?.data?.message ?? 'Failed to load cart.',
      });
    } finally {
      dispatch({ type: 'SET_LOADING', payload: false });
    }
  }, []);

  const addToCart = useCallback(async (menuItemId, quantity = 1) => {
    const payload = await addCartItem(menuItemId, quantity);
    dispatch({ type: 'SET_CART', payload });
    return payload;
  }, []);

  const changeQuantity = useCallback(async (itemId, quantity) => {
    const payload = await updateCartItem(itemId, quantity);
    dispatch({ type: 'SET_CART', payload });
    return payload;
  }, []);

  const removeItem = useCallback(async (itemId) => {
    const payload = await removeCartItem(itemId);
    dispatch({ type: 'SET_CART', payload });
    return payload;
  }, []);

  const clear = useCallback(async () => {
    await clearCart();
    dispatch({ type: 'SET_CART', payload: initialState });
  }, []);

  const checkout = useCallback(async ({ deliveryAddress, customerNote }) => {
    dispatch({ type: 'SET_CHECKOUT_LOADING', payload: true });
    dispatch({ type: 'SET_ERROR', payload: '' });

    try {
      const order = await placeOrder({
        delivery_address: deliveryAddress,
        customer_note: customerNote,
        idempotency_key: createIdempotencyKey(),
      });
      dispatch({ type: 'SET_LAST_ORDER', payload: order });
      await loadCart();
      return order;
    } catch (error) {
      const message = error?.response?.data?.message ?? 'Checkout failed.';
      dispatch({ type: 'SET_ERROR', payload: message });
      throw error;
    } finally {
      dispatch({ type: 'SET_CHECKOUT_LOADING', payload: false });
    }
  }, [loadCart]);

  const value = useMemo(
    () => ({
      ...state,
      loadCart,
      addToCart,
      changeQuantity,
      removeItem,
      clear,
      checkout,
    }),
    [state, loadCart, addToCart, changeQuantity, removeItem, clear, checkout],
  );

  return <CartContext.Provider value={value}>{children}</CartContext.Provider>;
}

export function useCart() {
  const context = useContext(CartContext);
  if (!context) {
    throw new Error('useCart must be used within CartProvider');
  }

  return context;
}
