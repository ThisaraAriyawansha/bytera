/**
 * User manual table of contents: highlights the section being read and closes the mobile
 * "Contents" drawer after a link is tapped.
 */
export default function manualToc(sectionIds) {
    return {
        active: sectionIds[0],
        observer: null,

        init() {
            this.observer = new IntersectionObserver((entries) => {
                entries.filter((entry) => entry.isIntersecting).forEach((entry) => {
                    this.active = entry.target.id;
                });
            }, { rootMargin: '-80px 0px -70% 0px' });

            sectionIds
                .map((id) => document.getElementById(id))
                .filter(Boolean)
                .forEach((section) => this.observer.observe(section));
        },

        destroy() {
            this.observer?.disconnect();
        },

        closeMobile() {
            this.$refs.mobile?.removeAttribute('open');
        },
    };
}
