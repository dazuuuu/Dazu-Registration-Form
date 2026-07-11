import { Link, NavLink, Outlet, useNavigate } from 'react-router-dom';
import { useEffect, useState } from 'react';
import { api, type User } from '../api/client';

export function PublicLayout() {
  const [user, setUser] = useState<User | null>(null);
  const [navOpen, setNavOpen] = useState(false);
  const navigate = useNavigate();

  useEffect(() => {
    api.me().then((r) => setUser(r.user)).catch(() => setUser(null));
  }, []);

  const logout = async () => {
    await api.logout();
    setUser(null);
    navigate('/');
  };

  return (
    <div>
      <header className="site-header">
        <div className="container site-header-inner">
          <Link to="/" className="brand">
            <span className="brand-icon">&lt;/&gt;</span>
            <span>
              <div className="brand-title">Dazu hub</div>
              <div className="brand-sub">Tech Skills Institute</div>
            </span>
          </Link>
          <button className="mobile-toggle" onClick={() => setNavOpen((v) => !v)} aria-label="Menu">
            <i className="fas fa-bars" />
          </button>
          <nav className={`nav ${navOpen ? 'open' : ''}`}>
            <NavLink to="/" end>Home</NavLink>
            <NavLink to="/courses">Courses</NavLink>
            <NavLink to="/testimonials">Testimonials</NavLink>
            <NavLink to="/about">About</NavLink>
            <NavLink to="/contact">Contact</NavLink>
          </nav>
          <div className="header-actions">
            <Link to="/apply" className="btn btn-primary">Apply</Link>
            {user ? (
              <>
                {user.role === 'student' && <Link to="/student" className="btn btn-outline">Portal</Link>}
                {user.role === 'tutor' && <Link to="/tutor" className="btn btn-outline">Tutor</Link>}
                {(user.role === 'admin' || user.role === 'superadmin') && (
                  <a href="/admin/dashboard.php" className="btn btn-outline">Admin</a>
                )}
                <button className="btn btn-outline" onClick={logout}>Logout</button>
              </>
            ) : (
              <Link to="/login" className="btn btn-outline"><i className="fas fa-user" /> Login</Link>
            )}
          </div>
        </div>
      </header>
      <Outlet />
      <footer className="site-footer">
        <div className="container footer-grid">
          <div>
            <div className="brand-title" style={{ color: '#fff' }}>Dazu hub</div>
            <p style={{ fontSize: '.88rem' }}>Practical, hands-on tech training in Nairobi, Kenya.</p>
          </div>
          <div>
            <h4>Quick Links</h4>
            <div style={{ display: 'grid', gap: 6 }}>
              <Link to="/courses">Courses</Link>
              <Link to="/apply">Apply</Link>
              <Link to="/testimonials">Testimonials</Link>
            </div>
          </div>
          <div>
            <h4>Portals</h4>
            <div style={{ display: 'grid', gap: 6 }}>
              <Link to="/student">Student</Link>
              <Link to="/tutor">Tutor</Link>
              <Link to="/login">Login</Link>
            </div>
          </div>
          <div>
            <h4>Contact</h4>
            <div style={{ display: 'grid', gap: 6 }}>
              <a href="mailto:info@dazuhub.com">info@dazuhub.com</a>
              <span>+254 750 116 600</span>
              <span>Nairobi, Kenya</span>
            </div>
          </div>
        </div>
        <div className="container footer-bottom">© {new Date().getFullYear()} Dazu Hub Tech Skills Institute</div>
      </footer>
    </div>
  );
}

export function PortalLayout({ base, links }: { base: string; links: { to: string; label: string }[] }) {
  return (
    <div className="portal-shell">
      <div className="portal-nav">
        <strong style={{ marginRight: 'auto' }}>Dazu Hub</strong>
        {links.map((l) => (
          <NavLink key={l.to} to={`${base}${l.to}`} end={l.to === ''}>{l.label}</NavLink>
        ))}
        <Link to="/">Public site</Link>
      </div>
      <div className="portal-content"><Outlet /></div>
    </div>
  );
}
