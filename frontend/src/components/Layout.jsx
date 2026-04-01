import { Link, NavLink } from 'react-router-dom';
import { Outlet } from 'react-router-dom';

const activeClass = ({ isActive }) => (isActive ? 'nav-link active' : 'nav-link');

export default function Layout() {
  return (
    <div className="app-shell">
      <header className="topbar">
        <Link className="brand" to="/">
          FoodHub
        </Link>
        <nav className="nav">
          <NavLink to="/restaurants" className={activeClass}>
            Restaurants
          </NavLink>
          <NavLink to="/cart" className={activeClass}>
            Cart
          </NavLink>
        </nav>
      </header>
      <main className="page-container">
        <Outlet />
      </main>
    </div>
  );
}
