export class RevisionGate {
    target = 0;
    private loading = false;
    private pending = false;

    observe(revision: number, applied: number): boolean {
        if (!Number.isSafeInteger(revision) || revision <= applied) return false;
        this.target = Math.max(this.target, revision);
        return true;
    }

    begin(): boolean {
        if (this.loading) {
            this.pending = true;
            return false;
        }
        this.loading = true;
        this.pending = false;
        return true;
    }

    finish(applied: number, succeeded: boolean): boolean {
        this.loading = false;
        return succeeded && (this.pending || this.target > applied);
    }
}
