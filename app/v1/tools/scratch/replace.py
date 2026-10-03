import sys
content = open('public/blogs/index.php').read()
lines = content.split('\n')
new_lines = """      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-x-6 gap-y-10">
        <?php foreach ($articles as $article): ?>
          <article class="flex flex-col group">
            <?php if (!empty($article['image'])): ?>
              <a href="<?php echo htmlspecialchars($article['url']); ?>" class="block w-full mb-4 overflow-hidden bg-slate-100">
                <img src="<?php echo htmlspecialchars($article['image']); ?>" alt="" class="w-full aspect-[4/3] object-cover group-hover:scale-105 transition-transform duration-500">
              </a>
            <?php endif; ?>
            <div class="flex flex-col flex-grow">
              <div class="flex items-center gap-2 text-[11px] text-slate-500 mb-2 font-medium tracking-wide">
                <span><?php echo htmlspecialchars(date('F j, Y', strtotime($article['date'] ?? 'now'))); ?></span>
                <span>•</span>
                <span><?php echo htmlspecialchars($article['author']); ?></span>
              </div>
              <h2 class="text-xl md:text-[22px] font-normal text-slate-900 leading-tight mb-3">
                <a href="<?php echo htmlspecialchars($article['url']); ?>" class="hover:text-green-600 transition-colors line-clamp-2"><?php echo htmlspecialchars($article['title']); ?></a>
              </h2>
              <p class="text-slate-500 text-[13px] font-normal leading-relaxed line-clamp-3 mb-4 flex-grow"><?php echo htmlspecialchars($article['summary']); ?></p>
              <a href="<?php echo htmlspecialchars($article['url']); ?>" class="text-[12px] font-semibold text-slate-900 hover:text-green-600 flex items-center gap-1 group-hover:gap-2 transition-all">
                Read more <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
              </a>
            </div>
          </article>
        <?php endforeach; ?>
      </div>""".split('\n')

content_new = '\n'.join(lines[:115]) + '\n' + '\n'.join(new_lines) + '\n' + '\n'.join(lines[137:])
open('public/blogs/index.php', 'w').write(content_new)
