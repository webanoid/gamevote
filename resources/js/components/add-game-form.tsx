import { Form } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import GameController from '@/actions/App/Http/Controllers/GameController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

export default function AddGameForm({
    disabled,
    limited,
}: {
    disabled: boolean;
    limited: boolean;
}) {
    return (
        <Form
            {...GameController.store.form()}
            options={{ preserveScroll: true }}
            resetOnSuccess
            className="grid gap-2"
        >
            {({ processing, errors }) => (
                <>
                    <Label htmlFor="name">Suggest a game</Label>
                    <div className="flex flex-col gap-2 sm:flex-row">
                        <Input
                            id="name"
                            name="name"
                            required
                            maxLength={100}
                            autoComplete="off"
                            placeholder="e.g. Ticket to Ride"
                            aria-invalid={errors.name ? true : undefined}
                            aria-describedby="name-hint"
                            disabled={disabled}
                        />
                        <Button
                            type="submit"
                            disabled={disabled || processing}
                            className="sm:w-auto"
                            data-test="add-game-button"
                        >
                            {processing ? <Spinner /> : <Plus />}
                            Add game
                        </Button>
                    </div>
                    <InputError message={errors.name} />
                    <p id="name-hint" className="text-xs text-muted-foreground">
                        {disabled
                            ? 'You can suggest a game again after midnight.'
                            : limited
                              ? 'Adding a game uses your action for today.'
                              : 'Add as many games as you like while limits are off.'}
                    </p>
                </>
            )}
        </Form>
    );
}
