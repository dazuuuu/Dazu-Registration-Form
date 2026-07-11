import React, { useState } from 'react';
import { useRouter } from '../router';
import { 
  Search, 
  Calendar, 
  Clock, 
  User, 
  ArrowLeft, 
  ArrowRight,
  BookOpen,
  Sparkles,
  Share2
} from 'lucide-react';

interface Article {
  title: string;
  category: 'Tech Guides' | 'Campus Life' | 'Career Tips' | 'AI Trends';
  summary: string;
  content: string[];
  author: string;
  date: string;
  readTime: string;
}

export default function Blog() {
  const { navigate } = useRouter();
  const [searchQuery, setSearchQuery] = useState('');
  const [selectedCategory, setSelectedCategory] = useState<string>('All');
  const [activeArticle, setActiveArticle] = useState<Article | null>(null);

  const categories = ['All', 'Tech Guides', 'Campus Life', 'Career Tips', 'AI Trends'];

  const articles: Article[] = [
    {
      title: "How to Switch to a Software Career in Kenya in 2026",
      category: "Career Tips",
      summary: "A practical step-by-step blueprint detailing how to successfully transition from non-technical fields into software engineering with local labs.",
      content: [
        "Transitioning into software engineering from another industry can feel daunting, but the high local demand for digital systems makes it highly lucrative in 2026.",
        "Step 1: Focus on hands-on practice. Avoid getting stuck in 'tutorial hell.' Build minor but fully functional tools that run locally on your system.",
        "Step 2: Understand the local tech ecosystem. In Kenya, platforms like M-Pesa, FinTech startups, and logistics companies define the major hiring sectors.",
        "Step 3: Build a solid GitHub presence. Highlight your portfolio. Rather than just taking notes, prove your capacity with live URLs."
      ],
      author: "Eng. Davis Kamau",
      date: "July 2, 2026",
      readTime: "5 min read"
    },
    {
      title: "Demystifying AI Agents: The Next Big Shift After ChatGPT",
      category: "AI Trends",
      summary: "Understand what custom AI agents are, how they automate complex multi-step workflows, and why they are replacing simple static prompt models.",
      content: [
        "In 2026, the technology conversation has shifted from simple chatbots to autonomous AI agents that make decisions, execute scripts, and integrate with external APIs.",
        "Unlike standard LLM chat portals which wait for an immediate message, an AI agent operates authoritatively based on higher-level instructions, executing sub-processes iteratively.",
        "Learning custom AI integrations, vector stores, and agent patterns will distinguish developers from standard coders over the coming years.",
        "At Dazu Hub, we design our AI curriculum precisely to cover these agentic workflows."
      ],
      author: "Dr. Catherine Wambui",
      date: "June 28, 2026",
      readTime: "7 min read"
    },
    {
      title: "Top 5 Cybersecurity Pitfalls of Thika Small Businesses",
      category: "Tech Guides",
      summary: "Simple security audits and crucial measures every local shop or MSME should implement immediately to safeguard data and prevent digital extortion.",
      content: [
        "Small businesses are frequently targeted by cyber criminals because they often lack enterprise-grade firewalls or structured security protocols.",
        "Pitfall 1: Weak shared Wi-Fi networks. Hosting client check-ins on the same subnet as your POS billing systems invites active packet capture.",
        "Pitfall 2: Neglecting basic firmware updates. Vulnerable router firmware acts as a prime gateway for threat operators.",
        "Pitfall 3: Lack of physical terminal security. Leaving administration panels open on shop counters is a severe risk."
      ],
      author: "Mark Mwenda",
      date: "June 15, 2026",
      readTime: "4 min read"
    },
    {
      title: "Student Spotlight: Coding for Social Impact in Kiambu County",
      category: "Campus Life",
      summary: "How Eunice and her student peers coded 'Thika Soko Hub' during their laboratory sessions to assist local vegetable vendors manage daily inventory.",
      content: [
        "We are proud to highlight our student team who identified a real bottleneck inside Thika's local markets: agricultural distribution.",
        "By utilizing standard react grids, local database storage, and quick payment APIs, they created an operational storefront prototype.",
        "At Dazu Hub, we believe technological education achieves its maximum height when aligned directly with solving societal friction points."
      ],
      author: "Eunice Wangari",
      date: "May 30, 2026",
      readTime: "3 min read"
    }
  ];

  const filteredArticles = articles.filter((article) => {
    const matchesSearch = 
      article.title.toLowerCase().includes(searchQuery.toLowerCase()) || 
      article.summary.toLowerCase().includes(searchQuery.toLowerCase());
    
    const matchesCategory = selectedCategory === 'All' || article.category === selectedCategory;

    return matchesSearch && matchesCategory;
  });

  return (
    <div className="bg-white min-h-screen py-16 text-slate-900">
      <div className="max-w-[1280px] mx-auto px-4 md:px-8 lg:px-12">
        
        {activeArticle ? (
          /* DETAILED ARTICLE VIEW */
          <div className="max-w-3xl mx-auto space-y-10 text-left">
            
            {/* Back button */}
            <button
              onClick={() => setActiveArticle(null)}
              className="flex items-center gap-2 text-sm font-bold text-slate-600 hover:text-green-600 transition-colors cursor-pointer"
            >
              <ArrowLeft className="h-4 w-4" />
              Back to all articles
            </button>

            {/* Article Header */}
            <div className="space-y-6">
              <span className="text-xs uppercase font-extrabold tracking-widest bg-green-50 text-green-700 border border-green-200 px-3 py-1 rounded-full">
                {activeArticle.category}
              </span>
              <h1 className="text-3xl sm:text-4xl md:text-5xl font-extrabold text-slate-900 tracking-tight leading-tight">
                {activeArticle.title}
              </h1>
              
              <div className="flex flex-wrap items-center gap-6 text-xs text-slate-500 font-bold border-y border-slate-100 py-4">
                <span className="flex items-center gap-1.5">
                  <User className="h-4 w-4 text-green-600" />
                  By {activeArticle.author}
                </span>
                <span className="flex items-center gap-1.5">
                  <Calendar className="h-4 w-4 text-green-600" />
                  {activeArticle.date}
                </span>
                <span className="flex items-center gap-1.5">
                  <Clock className="h-4 w-4 text-green-600" />
                  {activeArticle.readTime}
                </span>
              </div>
            </div>

            {/* Article Content */}
            <div className="space-y-6 text-sm sm:text-base text-slate-700 leading-relaxed font-semibold">
              {activeArticle.content.map((p, i) => (
                <p key={i}>{p}</p>
              ))}
            </div>

            {/* Practical Enrollment Callout */}
            <div className="p-8 bg-slate-50 border border-slate-200 rounded-2xl flex flex-col sm:flex-row items-center justify-between gap-6 mt-12">
              <div className="space-y-1">
                <h4 className="text-sm font-bold text-slate-900">Want to build systems like this?</h4>
                <p className="text-xs text-slate-500 font-semibold">Join the Q3 2026 intake at Dazu Hub Institute in Thika Town.</p>
              </div>
              <button
                onClick={() => navigate('/apply')}
                className="px-5 py-2.5 bg-green-600 hover:bg-green-700 text-white text-xs font-bold rounded-xl transition-all"
              >
                Enroll Now
              </button>
            </div>

          </div>
        ) : (
          /* BLOG ARTICLE LISTING VIEW */
          <div className="space-y-16">
            
            {/* Header info */}
            <div className="max-w-2xl text-left space-y-4">
              <span className="text-green-600 text-xs uppercase tracking-widest font-extrabold px-3 py-1 bg-green-50 border border-green-200 rounded-full w-fit">
                Latest Insights
              </span>
              <h1 className="text-4xl md:text-5xl font-extrabold tracking-tight text-slate-900">
                The Dazu Hub Blog
              </h1>
              <p className="text-slate-600 font-medium leading-relaxed">
                Stay updated with modern technologies, cybersecurity practices, coding tips, and inspiring campus activities from our physical labs in Thika.
              </p>
            </div>

            {/* Controls Bar */}
            <div className="grid grid-cols-1 lg:grid-cols-12 gap-6 items-center border-b border-slate-100 pb-8">
              
              {/* Search box */}
              <div className="relative lg:col-span-4">
                <span className="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-slate-400">
                  <Search className="h-5 w-5" />
                </span>
                <input
                  type="text"
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                  placeholder="Search articles, topics, authors..."
                  className="w-full pl-11 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 placeholder-slate-400 font-medium focus:outline-none focus:border-green-600 focus:bg-white text-sm"
                />
              </div>

              {/* Categories */}
              <div className="lg:col-span-8 flex flex-wrap items-center gap-1.5">
                {categories.map((cat) => (
                  <button
                    key={cat}
                    onClick={() => setSelectedCategory(cat)}
                    className={`px-4 py-2.5 text-xs font-bold uppercase tracking-wider rounded-xl transition-all border cursor-pointer ${
                      selectedCategory === cat
                        ? 'bg-green-600 text-white border-green-600 font-extrabold'
                        : 'bg-slate-50 text-slate-600 border-slate-200 hover:bg-slate-100'
                    }`}
                  >
                    {cat}
                  </button>
                ))}
              </div>

            </div>

            {/* Articles List */}
            {filteredArticles.length === 0 ? (
              <div className="text-center py-20 border border-dashed border-slate-200 rounded-2xl bg-slate-50">
                <BookOpen className="h-10 w-10 text-slate-400 mx-auto mb-3" />
                <h3 className="text-base font-bold text-slate-900">No articles matching your filters</h3>
                <button
                  onClick={() => {
                    setSearchQuery('');
                    setSelectedCategory('All');
                  }}
                  className="mt-4 px-4 py-2 bg-green-600 text-white text-xs font-bold rounded-xl"
                >
                  Show All Articles
                </button>
              </div>
            ) : (
              <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
                {filteredArticles.map((article, idx) => (
                  <div
                    key={idx}
                    onClick={() => setActiveArticle(article)}
                    className="bg-slate-50 border border-slate-200 rounded-2xl p-8 text-left cursor-pointer hover:border-green-600 hover:bg-white transition-all duration-300 flex flex-col justify-between h-[300px]"
                  >
                    <div className="space-y-4">
                      <div className="flex items-center justify-between">
                        <span className="text-[10px] font-bold text-green-700 bg-green-50 border border-green-100 rounded-full px-2.5 py-0.5 uppercase tracking-widest">
                          {article.category}
                        </span>
                        <span className="text-[10px] font-bold text-slate-400 uppercase tracking-widest">
                          {article.readTime}
                        </span>
                      </div>
                      <h3 className="text-xl font-extrabold text-slate-900 hover:text-green-600 transition-colors line-clamp-2">
                        {article.title}
                      </h3>
                      <p className="text-slate-600 text-xs leading-relaxed font-semibold line-clamp-3">
                        {article.summary}
                      </p>
                    </div>

                    <div className="pt-6 border-t border-slate-100 flex items-center justify-between text-xs font-bold">
                      <span className="text-slate-500">By {article.author}</span>
                      <span className="text-green-600 flex items-center gap-1">
                        Read Article <ArrowRight className="h-3.5 w-3.5" />
                      </span>
                    </div>
                  </div>
                ))}
              </div>
            )}

          </div>
        )}

      </div>
    </div>
  );
}
