@if (session('status'))
    <div class="mb-4 rounded-sm bg-[#f0fff2] dark:bg-[#00220a] border border-[#03F53B] text-[#0a7d1e] dark:text-[#44FF66] px-4 py-3 text-sm">
        {{ session('status') }}
    </div>
@endif
