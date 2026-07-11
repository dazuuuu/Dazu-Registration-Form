import ApplyForm from '../components/ApplyForm';

export default function ApplyPage() {
  return (
    <main className="section container" style={{ maxWidth: 640 }}>
      <div className="section-head">
        <h2>Course Application</h2>
        <p>Pay via M-Pesa, then complete your application below.</p>
      </div>
      <ApplyForm />
    </main>
  );
}
