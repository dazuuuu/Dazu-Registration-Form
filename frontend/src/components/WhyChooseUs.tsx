import React from 'react';

const WhyChooseUs: React.FC = () => {
    const features = [
        {
            icon: <svg xmlns="http://www.w3.org/2000/svg" className="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M12 14l9-5-9-5-9 5 9 5z" /><path d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-5.998 12.078 12.078 0 01.665-6.479L12 14z" /><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-5.998 12.078 12.078 0 01.665-6.479L12 14z" /></svg>,
            title: 'Expert Instructors',
            description: 'Learn from industry professionals with real-world AI experience.'
        },
        {
            icon: <svg xmlns="http://www.w3.org/2000/svg" className="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" /></svg>,
            title: 'Practical Learning',
            description: 'Engage in hands-on projects that build a portfolio of your skills.'
        },
        {
            icon: <svg xmlns="http://www.w3.org/2000/svg" className="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M12 6a2 2 0 012 2v8a2 2 0 01-2 2H8a2 2 0 01-2-2v-3a2 2 0 012-2h3z" /></svg>,
            title: 'Custom Solutions',
            description: 'We develop tailor-made AI tools and websites to fit your unique needs.'
        }
    ];
    return (
        <div className="bg-white dark:bg-slate-800 rounded-3xl shadow-lg border border-slate-200/80 dark:border-slate-700/80 p-8 sm:p-10">
            <div className="text-center mb-10">
                <h2 className="text-3xl sm:text-4xl font-extrabold text-slate-900 dark:text-slate-100">Why Choose <span className="gradient-text">Dazu AI Hub?</span></h2>
                <p className="mt-2 text-lg text-slate-600 dark:text-slate-400 max-w-2xl mx-auto">
                    We empower individuals and businesses to thrive in the age of AI.
                </p>
            </div>
            <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
                {features.map(feature => (
                    <div key={feature.title} className="text-center p-4">
                        <div className="flex items-center justify-center h-16 w-16 rounded-full bg-slate-100 dark:bg-slate-700 text-teal-500 dark:text-teal-400 mx-auto mb-4">
                            {feature.icon}
                        </div>
                        <h3 className="text-xl font-bold text-slate-800 dark:text-slate-200 mb-2">{feature.title}</h3>
                        <p className="text-slate-600 dark:text-slate-400">{feature.description}</p>
                    </div>
                ))}
            </div>
        </div>
    );
};

export default WhyChooseUs;