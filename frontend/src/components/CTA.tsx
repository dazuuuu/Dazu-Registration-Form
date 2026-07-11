import React from 'react';

interface CTAProps {
    onGetStartedClick: () => void;
}

const CTA: React.FC<CTAProps> = ({ onGetStartedClick }) => (
    <div className="rounded-3xl shadow-lg text-white p-8 sm:p-12 text-center" style={{background: 'var(--gradient)'}}>
        <h2 className="text-3xl sm:text-4xl font-extrabold mb-3">Ready to Start Your AI Journey?</h2>
        <p className="text-lg opacity-90 max-w-2xl mx-auto mb-6">
            Join hundreds of successful learners and businesses. Enroll in a course or get a quote for our services today!
        </p>
        <button
            onClick={onGetStartedClick}
            className="bg-white dark:bg-slate-800 text-transparent font-extrabold px-6 py-3 sm:px-8 sm:py-4 rounded-2xl hover:bg-slate-100 dark:hover:bg-slate-700 transition-all duration-300 shadow-lg hover:shadow-2xl text-base sm:text-lg transform hover:scale-105"
        >
            <span className="gradient-text">
                 Get Started Now
            </span>
        </button>
    </div>
);

export default CTA;