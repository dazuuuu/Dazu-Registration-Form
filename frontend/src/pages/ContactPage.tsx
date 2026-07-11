import { useState } from 'react';

export default function ContactPage() {
  const [sent, setSent] = useState(false);
  const [error, setError] = useState('');

  const submit = async (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    setError('');
    const fd = new FormData(e.currentTarget);
    try {
      const res = await fetch('/api/enquiry/submit.php', { method: 'POST', body: fd, credentials: 'include' });
      const data = await res.json();
      if (!data.success) throw new Error(data.message || 'Failed');
      setSent(true);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Could not send message');
    }
  };

  return (
    <main className="section container" style={{ maxWidth: 640 }}>
      <h1>Contact us</h1>
      {sent ? <div className="alert alert-success">Message sent. We will get back to you soon.</div> : (
        <form className="form-card" onSubmit={submit}>
          {error && <div className="alert alert-error">{error}</div>}
          <div className="field"><label>Name</label><input name="name" required /></div>
          <div className="field"><label>Email</label><input type="email" name="email" required /></div>
          <div className="field"><label>Phone</label><input name="phone" /></div>
          <div className="field"><label>Message</label><textarea name="message" rows={4} required /></div>
          <button className="btn btn-primary">Send message</button>
        </form>
      )}
    </main>
  );
}
