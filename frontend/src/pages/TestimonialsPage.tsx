import { useEffect, useState } from 'react';
import { api, type Testimonial } from '../api/client';

function embedUrl(t: Testimonial): string | null {
  if (!t.video_url) return null;
  if (t.video_provider === 'youtube' || t.video_url.includes('youtube') || t.video_url.includes('youtu.be')) {
    const id = t.video_url.match(/(?:v=|youtu\.be\/)([\w-]+)/)?.[1];
    return id ? `https://www.youtube.com/embed/${id}` : t.video_url;
  }
  return t.video_url;
}

export default function TestimonialsPage() {
  const [items, setItems] = useState<Testimonial[]>([]);
  useEffect(() => { api.testimonials().then((r) => setItems(r.data)).catch(() => {}); }, []);

  const videos = items.filter((t) => t.testimonial_type === 'video' || t.video_url);
  const text = items.filter((t) => t.testimonial_type !== 'video' && !t.video_url);

  return (
    <main className="section container">
      <div className="section-head">
        <h2>What our students say</h2>
        <p>Video and written testimonials from Dazu Hub learners.</p>
      </div>
      {videos.length > 0 && (
        <>
          <h3>Video testimonials</h3>
          <div className="video-grid" style={{ marginBottom: 24 }}>
            {videos.map((t) => {
              const src = embedUrl(t);
              return (
                <article key={t.id} className="video-card">
                  {src ? <iframe src={src} title={t.customer_name} allowFullScreen /> : null}
                  <div className="video-card-body">
                    <h3>{t.customer_name}</h3>
                    <p>{t.role || t.service_tag}</p>
                  </div>
                </article>
              );
            })}
          </div>
        </>
      )}
      <h3>Written reviews</h3>
      <div className="grid-3">
        {text.map((t) => (
          <article key={t.id} className="testimonial-card" style={{ padding: 14 }}>
            {t.avatar_url && <img src={t.avatar_url} alt="" style={{ width: 48, height: 48, borderRadius: '50%', marginBottom: 8 }} />}
            <h3 style={{ margin: '0 0 4px', fontSize: '1rem' }}>{t.customer_name}</h3>
            <p style={{ margin: 0, color: '#64748b', fontSize: '.8rem' }}>{t.role}</p>
            <p style={{ fontSize: '.9rem' }}>{t.testimonial_text}</p>
          </article>
        ))}
      </div>
    </main>
  );
}
