import { useEffect, useState } from 'react';
import { api, type Testimonial } from '../api/client';

const QuoteIcon: React.FC<{ className?: string }> = ({ className }) => (
  <svg aria-hidden="true" className={className} width="45" height="36" viewBox="0 0 45 36" fill="url(#quote-gradient)" xmlns="http://www.w3.org/2000/svg">
    <defs>
      <linearGradient id="quote-gradient" x1="0%" y1="0%" x2="100%" y2="100%">
        <stop offset="0%" style={{ stopColor: 'var(--gradient-start)', stopOpacity: 1 }} />
        <stop offset="100%" style={{ stopColor: 'var(--gradient-end)', stopOpacity: 1 }} />
      </linearGradient>
    </defs>
    <path d="M13.5938 36C10.0521 36 6.94271 34.8125 4.26562 32.4375C1.58854 30.0625 0.25 27.2188 0.25 23.9062C0.25 19.5312 1.58854 15.3438 4.26562 11.3438C6.94271 7.34375 10.4844 3.65625 14.8854 0.28125L19.75 4.65625C15.9062 7.40625 13.0625 10.25 11.2188 13.1875C9.375 16.125 8.45833 19.3438 8.45833 22.8438H13.5938V36ZM38.2812 36C34.7396 36 31.6302 34.8125 28.9531 32.4375C26.276 30.0625 24.9375 27.2188 24.9375 23.9062C24.9375 19.5312 26.276 15.3438 28.9531 11.3438C31.6302 7.34375 35.1719 3.65625 39.5729 0.28125L44.4375 4.65625C40.5938 7.40625 37.75 10.25 35.9062 13.1875C34.0625 16.125 33.1458 19.3438 33.1458 22.8438H38.2812V36Z" />
  </svg>
);

function embedUrl(t: Testimonial): string | null {
  if (!t.video_url) return null;
  if (t.video_provider === 'youtube' || t.video_url.includes('youtube') || t.video_url.includes('youtu.be')) {
    const id = t.video_url.match(/(?:v=|youtu\.be\/)([\w-]+)/)?.[1];
    return id ? `https://www.youtube.com/embed/${id}` : t.video_url;
  }
  return t.video_url;
}

const Testimonials: React.FC = () => {
  const [activeIndex, setActiveIndex] = useState(0);
  const [items, setItems] = useState<Testimonial[]>([]);

  useEffect(() => {
    api.testimonials().then((r) => setItems(r.data)).catch(() => setItems([]));
  }, []);

  const textItems = items.filter((t) => t.testimonial_type !== 'video' && !t.video_url);
  const videoItems = items.filter((t) => t.testimonial_type === 'video' || t.video_url);
  const carousel = textItems.length ? textItems : items;

  useEffect(() => {
    if (!carousel.length) return;
    const timer = setInterval(() => {
      setActiveIndex((prev) => (prev + 1) % carousel.length);
    }, 5000);
    return () => clearInterval(timer);
  }, [carousel.length]);

  const active = carousel[activeIndex];

  return (
    <div>
      <div className="mb-12 text-center">
        <h1 className="text-4xl md:text-5xl font-extrabold text-slate-900 dark:text-slate-100 mb-3">
          What Our <span className="gradient-text">Clients Say</span>
        </h1>
        <p className="mt-2 text-lg text-slate-600 dark:text-slate-400 max-w-2xl mx-auto">
          Real stories from Dazu Hub students and partners.
        </p>
      </div>

      {videoItems.length > 0 && (
        <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6 mb-12">
          {videoItems.map((t) => {
            const src = embedUrl(t);
            return (
              <article key={t.id} className="bg-white dark:bg-slate-800 rounded-3xl overflow-hidden border border-slate-200/80 dark:border-slate-700/80">
                {src && <iframe src={src} title={t.customer_name} className="w-full aspect-video" allowFullScreen />}
                <div className="p-4">
                  <h3 className="font-bold">{t.customer_name}</h3>
                  <p className="text-sm text-slate-500">{t.role || t.service_tag}</p>
                </div>
              </article>
            );
          })}
        </div>
      )}

      {active && (
        <div className="relative bg-white dark:bg-slate-800 rounded-3xl shadow-xl border border-slate-200/80 dark:border-slate-700/80 p-8 md:p-12 max-w-4xl mx-auto">
          <QuoteIcon className="absolute top-6 left-6 opacity-20" />
          <p className="text-xl md:text-2xl text-slate-700 dark:text-slate-300 leading-relaxed mb-8 relative z-10">
            “{active.testimonial_text}”
          </p>
          <div className="flex items-center gap-4">
            {active.avatar_url ? (
              <img src={active.avatar_url} alt="" className="w-14 h-14 rounded-full object-cover" />
            ) : (
              <div className="w-14 h-14 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center font-bold text-xl">
                {active.customer_name.charAt(0)}
              </div>
            )}
            <div>
              <p className="font-bold text-slate-900 dark:text-slate-100">{active.customer_name}</p>
              <p className="text-slate-500 text-sm">{active.role || active.service_tag}</p>
            </div>
          </div>
          {carousel.length > 1 && (
            <div className="flex justify-center gap-2 mt-8">
              {carousel.map((_, i) => (
                <button
                  key={i}
                  type="button"
                  aria-label={`Show testimonial ${i + 1}`}
                  onClick={() => setActiveIndex(i)}
                  className={`w-2.5 h-2.5 rounded-full ${i === activeIndex ? 'bg-emerald-500' : 'bg-slate-300'}`}
                />
              ))}
            </div>
          )}
        </div>
      )}
    </div>
  );
};

export default Testimonials;
