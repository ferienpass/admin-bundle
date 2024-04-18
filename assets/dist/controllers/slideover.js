'use strict';
import { Controller } from '@hotwired/stimulus';
// @ts-ignore
import { enter, leave } from "el-transition";
//import {Component, getComponent} from '@symfony/ux-live-component';
export default class default_1 extends Controller {
    show() {
        this.element.classList.remove("hidden");
        enter(this.panelTarget);
    }
    hide() {
        Promise.all([
            leave(this.panelTarget),
        ]).then(() => {
            this.element.classList.add("hidden");
        });
    }
}
default_1.values = {};
default_1.targets = ["panel"];
