<script data-navigate-once>
    (() => {
        const closeSidebarOnMobile = () => {
            if (window.innerWidth >= 1024) {
                return
            }

            const sidebar = window.Alpine?.store('sidebar')

            if (sidebar?.isOpen) {
                sidebar.close()
            }
        }

        const scheduleClose = () => window.setTimeout(closeSidebarOnMobile, 0)

        document.addEventListener('alpine:init', scheduleClose)
        document.addEventListener('alpine:initialized', scheduleClose)
        document.addEventListener('livewire:navigated', scheduleClose)
        scheduleClose()
    })()
</script>
