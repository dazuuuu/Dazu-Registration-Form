import { Link } from 'react-router-dom';
import { CourseGrid } from '../components/CourseCard';

export default function HomePage() {
  return (
  <main>
    <section className="container hero">
      <div>
        <p style={{ color: '#00a859', fontWeight: 700 }}>Learn. Build. Succeed.</p>
        <h1>Upgrade Your Tech Skills.<span>Build Your Future.</span></h1>
        <p>Practical, hands-on training in web development, cybersecurity, data analytics, and AI.</p>
        <div style={{ display: 'flex', gap: 10, marginTop: 18, flexWrap: 'wrap' }}>
          <Link to="/courses" className="btn btn-primary">Explore Courses</Link>
          <Link to="/apply" className="btn btn-outline">Apply Now</Link>
        </div>
      </div>
      <div className="hero-photo">
        <img src="https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?w=800&q=80" alt="Student learning" />
      </div>
    </section>
    <section className="section container">
      <div className="section-head"><h2>Popular Courses</h2><p>Start with a course that matches your goals.</p></div>
      <CourseGrid limit={4} />
      <div style={{ marginTop: 16 }}><Link to="/courses" className="btn btn-outline">View all courses</Link></div>
    </section>
    <section className="section container">
      <div className="grid-2">
        <div className="panel" style={{ padding: 16 }}><h3>Hands-on projects</h3><p>Build real portfolio work with mentor feedback.</p></div>
        <div className="panel" style={{ padding: 16 }}><h3>Flexible payments</h3><p>Monthly, 3-month, or full payment via M-Pesa.</p></div>
      </div>
    </section>
  </main>
  );
}
